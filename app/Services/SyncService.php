<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\ProjectionService;

/**
 * Service for synchronizing events between branch and cloud.
 */
class SyncService
{
    protected $projectionService;
    protected $cloudUrl;

    public function __construct(ProjectionService $projectionService)
    {
        $this->projectionService = $projectionService;
        $this->cloudUrl = config('services.cloud.url', env('CLOUD_URL', 'https://api.pharmacy-cloud.com'));
    }

    /**
     * Push unsynced local events to the cloud.
     *
     * @param string $accountId
     * @return int Number of events synced
     */
    public function pushToCloud(string $accountId)
    {
        // 1. Get unsynced events
        $events = DB::table('event_ledger')
            ->where('account_id', $accountId)
            ->whereNull('synced_at')
            ->limit(50) // Batch limit
            ->orderBy('local_sequence', 'asc')
            ->get();

        if ($events->isEmpty()) {
            return 0;
        }

        // 2. Send to Cloud
        // In reality, this should be authenticated (Sanctum/Oauth)
        $response = Http::post("{$this->cloudUrl}/api/v1/sync/receive", [
            'events' => $events->toArray()
        ]);

        if ($response->successful()) {
            // 3. Mark as synced
            $eventIds = $events->pluck('id');
            DB::table('event_ledger')
                ->whereIn('id', $eventIds)
                ->update(['synced_at' => now()]);

            return $events->count();
        }

        throw new \Exception("Sync Push Failed: " . $response->body());
    }

    /**
     * Pull new events from the cloud for a specific account.
     *
     * @param string $accountId
     * @return int Number of events pulled
     */
    public function pullFromCloud(string $accountId)
    {
        // 1. Get last known Global Sequence locally
        $lastGlobal = DB::table('event_ledger')->max('global_sequence') ?? 0;

        // 2. Request new events from Cloud
        $response = Http::get("{$this->cloudUrl}/api/v1/sync/serve", [
            'after_global_sequence' => $lastGlobal,
            'limit' => 100
        ]);

        if (!$response->successful()) {
            throw new \Exception("Sync Pull Failed: " . $response->body());
        }

        $cloudEvents = $response->json('events');
        $processed = 0;

        // 3. Process Incoming
        foreach ($cloudEvents as $eventData) {
            $processed += $this->processIncomingEvent((array)$eventData);
        }

        return $processed;
    }

    /**
     * Insert and Project a single incoming event (Replay logic).
     */
    protected function processIncomingEvent(array $event)
    {
        // Check if we already have it (idempotency)
        $exists = DB::table('event_ledger')->where('id', $event['id'])->exists();
        if ($exists) {
            // Check if we need to update global_sequence if it was null locally
            if (isset($event['global_sequence'])) {
                DB::table('event_ledger')
                    ->where('id', $event['id'])
                    ->whereNull('global_sequence')
                    ->update(['global_sequence' => $event['global_sequence'], 'synced_at' => now()]);
            }
            return 0;
        }

        // It is a NEW event (from another branch or cloud)
        // Convert array payload back to json if needed, or insert assumes array maps to columns
        // Laravel DB insert needs explicit json encoding for array columns if raw array passed
        if (is_array($event['event_payload'])) {
            $event['event_payload'] = json_encode($event['event_payload']);
        }

        DB::table('event_ledger')->insert([
            'id' => $event['id'],
            'account_id' => $event['account_id'],
            'device_id' => $event['device_id'],
            'actor_user_id' => $event['actor_user_id'] ?? null,
            'event_type' => $event['event_type'],
            'event_category' => $event['event_category'] ?? null,
            'event_version' => $event['event_version'] ?? 1,
            'event_payload' => $event['event_payload'],
            'local_sequence' => $event['local_sequence'],
            'event_time_utc' => $event['event_time_utc'],
            'event_hash' => $event['event_hash'],
            'received_at_cloud' => $event['received_at_cloud'] ?? null,
            'metadata' => $event['metadata'] ?? null,
            'global_sequence' => $event['global_sequence'] ?? null,
            'synced_at' => now() // It came from cloud, so it is synced
        ]);

        // REPLAY: Trigger Projection
        // We cast generic object to structure expected by Projector if needed
        $eventObj = (object)$event;
        $eventObj->event_payload = json_decode($event['event_payload'], true);

        $this->projectionService->projectEvent($eventObj);

        return 1;
    }
}
