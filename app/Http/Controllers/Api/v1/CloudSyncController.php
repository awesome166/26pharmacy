<?php

namespace App\Http\Controllers\Api\v1;

use App\Events\SyncUpdateAvailable;
use App\Http\Controllers\Controller;
use App\Services\EventLedgerService;
use App\Services\ProjectionService;
use App\Services\EventValidationService;
use App\Services\Sync\DownloadAuthorization;
use App\Services\Sync\EventPayloadAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloudSyncController extends Controller
{
    public function __construct(
        private readonly EventLedgerService $ledger,
        private readonly ProjectionService $projections,
        private readonly DownloadAuthorization $downloads,
        private readonly EventValidationService $validation,
        private readonly EventPayloadAuthorization $payloadAuthorization,
    ) {}

    public function receiveBatch(Request $request)
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'max:500'],
            'events.*.id' => ['required', 'string'],
            'events.*.device_id' => ['required', 'string'],
            'events.*.actor_user_id' => ['nullable', 'string'],
            'events.*.event_type' => ['required', 'string', 'max:100'],
            'events.*.event_category' => ['nullable', 'string', 'max:100'],
            'events.*.event_version' => ['nullable', 'integer', 'min:1'],
            'events.*.event_hash' => ['required', 'string', 'size:64'],
            'events.*.previous_hash' => ['nullable', 'string', 'size:64'],
            'events.*.account_id' => ['required', 'string'],
            'events.*.branch_id' => ['required', 'string'],
            'events.*.local_sequence' => ['required', 'integer', 'min:1'],
            'events.*.event_time_utc' => ['required', 'date'],
            'events.*.event_payload' => ['required'],
        ]);

        $device = $request->attributes->get('sync_device');
        $inserted = [];

        DB::transaction(function () use ($validated, $device, &$inserted) {
            $state = DB::table('sync_global_state')->where('id', 1)->lockForUpdate()->first();
            $global = (int) $state->last_sequence;
            DB::table('device_stream_heads')->insertOrIgnore([
                'device_id' => $device->device_id, 'last_local_sequence' => 0,
                'last_event_hash' => str_repeat('0', 64), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $head = DB::table('device_stream_heads')->where('device_id', $device->device_id)->lockForUpdate()->first();

            foreach ($validated['events'] as $input) {
                if ($input['device_id'] !== $device->device_id
                    || $input['account_id'] !== $device->account_id
                    || $input['branch_id'] !== $device->branch_id) {
                    throw ValidationException::withMessages(['events' => 'Event ownership does not match the authenticated device.']);
                }

                $existing = DB::table('event_ledger')->where('id', $input['id'])->first();
                if ($existing) {
                    if ($existing->device_id !== $device->device_id || $existing->event_hash !== $input['event_hash']) {
                        throw ValidationException::withMessages(['events' => 'An event ID was reused with different content.']);
                    }
                    // A duplicate is an acknowledgement, not the stream head.
                    // Keeping the real head avoids accepting a new event after an
                    // older retry with a stale sequence.
                    continue;
                }

                $expectedSequence = (int) $head->last_local_sequence + 1;
                $expectedPreviousHash = $head->last_event_hash;
                if ((int) $input['local_sequence'] !== $expectedSequence || ($input['previous_hash'] ?? null) !== $expectedPreviousHash) {
                    throw ValidationException::withMessages(['events' => 'Event sequence or hash-chain continuity is invalid.']);
                }

                try {
                    $payload = is_string($input['event_payload'])
                        ? json_decode($input['event_payload'], true, 512, JSON_THROW_ON_ERROR)
                        : $input['event_payload'];
                } catch (\JsonException) {
                    throw ValidationException::withMessages(['events' => 'INVALID_EVENT_PAYLOAD']);
                }
                $payload = $this->validation->validateForCloud(
                    $input['event_type'], (int) ($input['event_version'] ?? 1), $payload
                );
                $this->payloadAuthorization->authorize(
                    (string) $device->account_id, (string) $device->branch_id, $input['event_type'], $payload
                );
                $hashInput = [
                    'id' => $input['id'], 'account_id' => $input['account_id'], 'branch_id' => $input['branch_id'],
                    'device_id' => $input['device_id'], 'actor_user_id' => $input['actor_user_id'] ?? null,
                    'event_type' => $input['event_type'], 'event_version' => $input['event_version'] ?? 1,
                    'event_payload' => $payload, 'local_sequence' => (int) $input['local_sequence'],
                    'event_time_utc' => \Carbon\CarbonImmutable::parse($input['event_time_utc'])->toISOString(),
                    'previous_hash' => $input['previous_hash'],
                ];
                if (!hash_equals($input['event_hash'], $this->ledger->computeEventHash($hashInput))) {
                    throw ValidationException::withMessages(['events' => 'Event integrity verification failed.']);
                }

                $global++;
                DB::table('event_ledger')->insert([
                    'id' => $input['id'], 'account_id' => $device->account_id, 'branch_id' => $device->branch_id,
                    'device_id' => $device->device_id, 'actor_user_id' => $input['actor_user_id'] ?? null,
                    'event_type' => $input['event_type'], 'event_category' => $input['event_category'] ?? null,
                    'event_version' => $input['event_version'] ?? 1,
                    'event_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'local_sequence' => $input['local_sequence'], 'global_sequence' => $global,
                    'event_time_utc' => $input['event_time_utc'], 'event_hash' => $input['event_hash'],
                    'previous_hash' => $input['previous_hash'], 'sync_status' => 'synced',
                    'received_at_cloud' => now(), 'synced_at' => now(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $head->last_local_sequence = (int) $input['local_sequence'];
                $head->last_event_hash = $input['event_hash'];
                $inserted[] = $input['id'];
            }

            DB::table('sync_global_state')->where('id', 1)->update(['last_sequence' => $global]);
            DB::table('device_stream_heads')->where('device_id', $device->device_id)->update([
                'last_local_sequence' => $head->last_local_sequence,
                'last_event_hash' => $head->last_event_hash,
                'updated_at' => now(),
            ]);
        });

        // Project every submitted event that is not yet acknowledged as projected.
        // This deliberately includes events inserted by a previous failed request so
        // an HTTP retry can repair a projection without duplicating the ledger row.
        $eventIds = collect($validated['events'])->pluck('id');
        $pendingProjectionIds = DB::table('event_ledger')
            ->whereIn('id', $eventIds)
            ->where('device_id', $device->device_id)
            ->whereNotIn('id', DB::table('projected_events')->select('event_id'))
            ->orderBy('global_sequence')
            ->pluck('id');

        foreach ($pendingProjectionIds as $id) {
            $this->projections->projectEvent(DB::table('event_ledger')->where('id', $id)->first());
        }
        if ($inserted) {
            SyncUpdateAvailable::dispatch($device->account_id);
        }

        $assignments = DB::table('event_ledger')->whereIn('id', $eventIds)
            ->where('device_id', $device->device_id)->pluck('global_sequence', 'id');

        return response()->json(['processed' => count($inserted), 'assignments' => $assignments]);
    }

    public function serveBatch(Request $request)
    {
        $validated = $request->validate(['after_global_sequence' => ['nullable', 'integer', 'min:0'], 'limit' => ['nullable', 'integer', 'min:1', 'max:500']]);
        $device = $request->attributes->get('sync_device');
        $policy = $this->downloads->continuous($device);
        $allowedBranches = json_decode($policy->allowed_branch_ids, true) ?: [];
        $events = DB::table('event_ledger')->where('account_id', $device->account_id)
            ->when($allowedBranches !== [], fn ($query) => $query->whereIn('branch_id', $allowedBranches))
            ->where('global_sequence', '>', $validated['after_global_sequence'] ?? 0)
            ->orderBy('global_sequence')->limit($validated['limit'] ?? 100)->get();
        return response()->json([
            'events' => $events,
            'access_snapshot' => app(\App\Services\AccessSnapshotService::class)->export((string) $device->account_id),
        ]);
    }

    public function fullRestore(Request $request)
    {
        // The legacy endpoint was an unsafe, unbounded destructive restore.
        // Restore now requires a staged, device-bound grant protocol.
        abort(410, 'RESTORE_PROTOCOL_UPGRADE_REQUIRED');
    }
}
