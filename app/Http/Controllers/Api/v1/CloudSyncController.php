<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\EventLedgerService;

class CloudSyncController extends Controller
{
    /**
     * Receive a batch of events from a local branch.
     * Use append-only logic.
     */
    public function receiveBatch(Request $request)
    {
        // In a real app, strict validation of the hash chain from the branch is needed here.
        // For this implementation, we accept valid events and assign global IDs.

        $request->validate([
            'events' => 'required|array',
            'events.*.event_id' => 'required',
            'events.*.hash' => 'required',
            'events.*.previous_hash' => 'present',
            'events.*.branch_id' => 'required',
        ]);

        $events = $request->input('events', []);
        $affected = 0;

        DB::transaction(function () use ($events, &$affected) {
            // Lock for global sequencing if needed, or rely on auto-increment/sequence generator
            // Here we assume a simple centralized SQL sequence or max+1 strategy for demonstration


            $lastGlobal = DB::table('event_ledger')->max('global_sequence') ?? 0;

            foreach ($events as $eventData) {
                // Check idempotency: does this event_id already exist?
                $exists = DB::table('event_ledger')->where('event_id', $eventData['event_id'])->exists();

                if (!$exists) {
                    $lastGlobal++;

                    // Assign global sequence
                    $eventData['global_sequence'] = $lastGlobal;
                    $eventData['received_at_cloud'] = now();

                    // Insert
                    DB::table('event_ledger')->insert($eventData);
                    $affected++;
                }
            }
        });

        return response()->json(['processed' => $affected]);
    }

    /**
     * Serve events to a local branch that occurred after their last known global sequence.
     */
    public function serveBatch(Request $request)
    {
        $lastKnownGlobal = $request->query('after_global_sequence', 0);
        $limit = $request->query('limit', 100);

        $events = DB::table('event_ledger')
            ->where('global_sequence', '>', $lastKnownGlobal)
            ->orderBy('global_sequence', 'asc')
            ->limit($limit)
            ->get();

        return response()->json(['events' => $events]);
    }
}
