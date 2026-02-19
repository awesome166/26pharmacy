<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\SyncUpdateAvailable;

class CloudSyncController extends Controller
{
    /**
     * Receive a batch of events from a local branch.
     * Use append-only logic.
     */
    public function receiveBatch(Request $request)
    {
        $request->validate([
            'events' => 'required|array',
            'events.*.id' => 'required', // Changed from event_id to id
            'events.*.event_hash' => 'required', // Changed from hash to event_hash
            // 'events.*.previous_hash' => 'present',
            // 'events.*.branch_id' => 'required', // account_id check
        ]);

        $events = $request->input('events', []);
        $affected = 0;
        $tenantId = null;

        DB::transaction(function () use ($events, &$affected, &$tenantId) {
            $lastGlobal = DB::table('event_ledger')->max('global_sequence') ?? 0;

            foreach ($events as $eventData) {
                // Idempotency check using 'id'
                $exists = DB::table('event_ledger')->where('id', $eventData['id'])->exists();

                // Capture tenant ID for broadcasting
                if (!$tenantId && isset($eventData['account_id'])) {
                    $tenantId = $eventData['account_id'];
                }

                if (!$exists) {
                    $lastGlobal++;

                    // Assign global sequence
                    $eventData['global_sequence'] = $lastGlobal;
                    $eventData['received_at_cloud'] = now();
                    $eventData['synced_at'] = now();

                    // Remove any fields that shouldn't be inserted directly if data doesn't match schema exactly
                    // For now assuming payload matches DB schema columns.
                    // Validation of payload structure is recommended in production.

                    DB::table('event_ledger')->insert($eventData);
                    $affected++;
                }
            }
        });

        if ($affected > 0 && $tenantId) {
            SyncUpdateAvailable::dispatch($tenantId);
        }

        return response()->json(['processed' => $affected]);
    }

    /**
     * Serve events to a local branch that occurred after their last known global sequence.
     */
    public function serveBatch(Request $request)
    {
        $lastKnownGlobal = $request->query('after_global_sequence', 0);
        $limit = $request->query('limit', 100);

        // Security: Filter by tenant/account_id if needed, assuming auth middleware handles context
        // $accountId = $request->user()->account_id ?? ...

        $events = DB::table('event_ledger')
            ->where('global_sequence', '>', $lastKnownGlobal)
            ->orderBy('global_sequence', 'asc')
            ->limit($limit)
            ->get();

        return response()->json(['events' => $events]);
    }
}
