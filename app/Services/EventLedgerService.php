<?php

namespace App\Services;

use AbacPermissions\Tenancy\TenantContext;

/**
 * Service for managing the event ledger (The Source of Truth).
 */
class EventLedgerService
{
    /**
     * Emit a new event to the ledger.
     *
     * @param array $eventData
     * @return object
     */
    public function emitEvent(array $eventData)
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($eventData) {
            app(EventValidationService::class)->assertSupported(
                $eventData['event_type'], (int) ($eventData['event_version'] ?? 1)
            );
            // Scope automatically handles account_id filtering via UsesTenant
            $accountId = $eventData['account_id'] ?? app(TenantContext::class)->getAccountId();
            $device = \App\Models\Device::where('device_id', $eventData['device_id'])
                ->where('account_id', $accountId)
                ->where('trust_status', 'active')
                ->firstOrFail();

            // A persistent row exists even for a new stream, unlike locking the
            // last ledger event (which cannot lock an empty result set).
            \Illuminate\Support\Facades\DB::table('device_stream_heads')->insertOrIgnore([
                'device_id' => $device->device_id, 'last_local_sequence' => 0,
                'last_event_hash' => str_repeat('0', 64), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $head = \Illuminate\Support\Facades\DB::table('device_stream_heads')
                ->where('device_id', $device->device_id)->lockForUpdate()->first();
            $sequence = (int) $head->last_local_sequence + 1;
            $previousHash = (string) $head->last_event_hash;

            $id = \Illuminate\Support\Str::ulid();
            // Database date serialization is second-precision; hash the same canonical value sent to the cloud.
            $eventTime = now()->startOfSecond();
            $hash = $this->computeEventHash([
                'id' => (string) $id,
                'account_id' => (string) $accountId,
                'branch_id' => (string) $device->branch_id,
                'device_id' => (string) $device->device_id,
                'actor_user_id' => $eventData['actor_user_id'] ?? null,
                'event_type' => $eventData['event_type'],
                'event_version' => $eventData['event_version'] ?? 1,
                'event_payload' => $eventData['event_payload'],
                'local_sequence' => $sequence,
                'event_time_utc' => $eventTime->toISOString(),
                'previous_hash' => $previousHash,
            ]);

            $globalSequence = null;
            if (config('sync.role') === 'parent') {
                $state = \Illuminate\Support\Facades\DB::table('sync_global_state')
                    ->where('id', 1)->lockForUpdate()->first();
                $globalSequence = ((int) ($state->last_sequence ?? 0)) + 1;
                \Illuminate\Support\Facades\DB::table('sync_global_state')
                    ->where('id', 1)->update(['last_sequence' => $globalSequence]);
            }

            \App\Models\EventLedger::create([
                'id' => $id,
                'account_id' => $accountId,
                'branch_id' => $device->branch_id,
                'device_id' => $device->device_id,
                'actor_user_id' => $eventData['actor_user_id'] ?? null,
                'event_type' => $eventData['event_type'],
                'event_payload' => $eventData['event_payload'], // Casts handle json encoding
                'local_sequence' => $sequence,
                'event_time_utc' => $eventTime,
                'event_hash' => $hash,
                'previous_hash' => $previousHash,
                'global_sequence' => $globalSequence,
                'sync_status' => $globalSequence ? 'synced' : 'pending',
                'synced_at' => $globalSequence ? now() : null,
                'received_at_cloud' => $globalSequence ? now() : null,
            ]);
            \Illuminate\Support\Facades\DB::table('device_stream_heads')->where('device_id', $device->device_id)
                ->update(['last_local_sequence' => $sequence, 'last_event_hash' => $hash, 'updated_at' => now()]);

            $eventObject = (object) [
                'id' => $id,
                'account_id' => $accountId,
                'branch_id' => $device->branch_id,
                'event_type' => $eventData['event_type'],
                'event_payload' => $eventData['event_payload'],
                'event_time_utc' => $eventTime,
            ];

            // Dispatch job for processing
            if ($eventData['project_locally'] ?? true) {
                \App\Jobs\ProcessLedgerEventJob::dispatch($eventObject);
            } else {
                \Illuminate\Support\Facades\DB::table('projected_events')->insertOrIgnore([
                    'event_id' => (string) $id, 'projected_at' => now(),
                ]);
            }
            if (config('sync.role') === 'child') {
                \App\Jobs\SyncEventsToCloudJob::dispatch((string) $accountId, (string) $device->device_id);
            } else {
                \App\Events\SyncUpdateAvailable::dispatch((string) $accountId);
            }

            return (object) ['id' => $id, 'hash' => $hash, 'global_sequence' => $globalSequence];
        });
    }

    /**
     * Compute a SHA-256 hash for an event to ensure integrity.
     *
     * @param array $payload
     * @param string $previousHash
     * @return string
     */
    public function computeHash(array $payload, string $previousHash)
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES) . $previousHash);
    }

    public function computeEventHash(array $event): string
    {
        return hash('sha256', json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Retrieve events for a specific branch.
     *
     * @param string $accountid
     * @param int $sinceSequence
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEvents(string $accountid, int $sinceSequence = 0)
    {
        return \App\Models\EventLedger::query()
            // account_id filter handled by UsesTenant scope
            ->where('local_sequence', '>', $sinceSequence)
            ->orderBy('local_sequence', 'asc')
            ->get();
    }
}
