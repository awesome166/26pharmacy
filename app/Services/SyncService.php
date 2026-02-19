<?php

namespace App\Services;

use App\Models\EventLedger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SyncService
{
    protected $role;
    protected $clientId;
    protected $cloudUrl;

    public function __construct()
    {
        $this->role = env('SYNC_ROLE', 'child');
        $this->clientId = env('SYNC_CLIENT_ID');
        $this->cloudUrl = env('CLOUD_URL');
    }

    public function isParent()
    {
        return $this->role === 'parent';
    }

    public function isChild()
    {
        return $this->role === 'child';
    }

    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * Push unsynced events to the parent.
     */
    public function push()
    {
        if (!$this->isChild()) {
            return;
        }

        $events = DB::table('event_ledger')
            ->whereNull('global_sequence')
            ->orderBy('local_sequence', 'asc')
            ->limit(100)
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'X-Sync-Client-Id' => $this->clientId,
                'Authorization' => 'Bearer ' . env('SYNC_API_TOKEN'),
                'Accept' => 'application/json',
            ])->post("{$this->cloudUrl}/api/v1/sync/receive", [
                'events' => $events->toArray(),
            ]);

            if ($response->successful()) {
                $processed = $response->json('processed');
                Log::info("Sync Push: Processed {$processed} events.");
                // We don't update global_sequence here immediately;
                // we wait for the Pull to confirm global_sequence assignment from parent
                // OR we can mark them as "pushed" locally if we had a status column.
                // For now, we rely on the parent to ignore duplicates if we push again.
            } else {
                Log::error("Sync Push Failed: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Sync Push Error: " . $e->getMessage());
        }
    }

    /**
     * Pull new events from the parent.
     */
    public function pull()
    {
        if (!$this->isChild()) {
            return;
        }

        $lastGlobal = DB::table('event_ledger')->max('global_sequence') ?? 0;

        try {
            $response = Http::withHeaders([
                'X-Sync-Client-Id' => $this->clientId,
                'Authorization' => 'Bearer ' . env('SYNC_API_TOKEN'),
                'Accept' => 'application/json',
            ])->get("{$this->cloudUrl}/api/v1/sync/serve", [
                'after_global_sequence' => $lastGlobal,
                'limit' => 100,
            ]);

            if ($response->successful()) {
                $events = $response->json('events');
                if (empty($events)) {
                    return;
                }

                foreach ($events as $event) {
                    $this->ingestEvent((array) $event);
                }

                Log::info("Sync Pull: Ingested " . count($events) . " events.");
            } else {
                Log::error("Sync Pull Failed: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Sync Pull Error: " . $e->getMessage());
        }
    }

    protected function ingestEvent(array $eventData)
    {
        // Check if we already have this event (idempotency)
        $existing = DB::table('event_ledger')->where('id', $eventData['id'])->first();

        if ($existing) {
            // If we have it, but it doesn't have a global_sequence, update it.
            if (is_null($existing->global_sequence)) {
                DB::table('event_ledger')
                    ->where('id', $eventData['id'])
                    ->update([
                        'global_sequence' => $eventData['global_sequence'],
                        'synced_at' => now(),
                    ]);
            }
            return;
        }

        // Insert new event from parent
        DB::table('event_ledger')->insert([
            'id' => $eventData['id'],
            'account_id' => $eventData['account_id'] ?? null,
            'device_id' => $eventData['device_id'] ?? 'cloud', // Default if missing
            'actor_user_id' => $eventData['actor_user_id'] ?? null,
            'event_type' => $eventData['event_type'],
            'event_payload' => is_string($eventData['event_payload']) ? $eventData['event_payload'] : json_encode($eventData['event_payload']),
            'occurred_at' => $eventData['occurred_at'] ?? $eventData['event_time_utc'], // Handle both if mismatched
            'event_time_utc' => $eventData['event_time_utc'],
            'event_hash' => $eventData['event_hash'],
            'local_sequence' => null, // Cloud events don't strictly technically have a local sequence unless we assign one for ordering
            'global_sequence' => $eventData['global_sequence'],
            'synced_at' => now(),
            'received_at_cloud' => $eventData['received_at_cloud'] ?? now(),
        ]);

        // Trigger local processors for this event here
        // EventLedgerService::replay($eventData);
    }
}
