<?php

namespace App\Services;

use App\Models\EventLedger;
use App\Services\ProjectionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

class SyncService
{
    protected string $role;
    protected ?string $clientId;
    protected ?string $cloudUrl;
    protected ?string $apiToken;
    protected int $batchSize;
    protected ?string $runtimeAccountId = null;

    public function __construct()
    {
        $this->role = config('sync.role', 'child');
        $this->clientId = config('sync.client_id');
        $this->cloudUrl = config('sync.cloud_url');
        $this->apiToken = config('sync.api_token');
        $this->batchSize = config('sync.batch_size', 100);
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        if ($accountId && (!$this->clientId || !$this->apiToken)) {
            $settings = DB::table('system_settings')
                ->where('account_id', $accountId)
                ->whereIn('key', ['sync_client_id', 'sync_api_token'])
                ->pluck('value', 'key');
            $this->clientId = $this->clientId ?: $settings->get('sync_client_id');
            if (!$this->apiToken && $settings->get('sync_api_token')) {
                try {
                    $this->apiToken = Crypt::decryptString($settings->get('sync_api_token'));
                } catch (\Throwable) {
                    $this->apiToken = null;
                }
            }
        }
    }

    public function forAccount(?string $accountId, ?string $deviceId = null): self
    {
        $this->runtimeAccountId = $accountId;

        if ($deviceId) {
            $this->clientId = $deviceId;
        }

        if ($accountId) {
            $settings = DB::table('system_settings')
                ->where('account_id', $accountId)
                ->whereIn('key', ['sync_client_id', 'sync_api_token'])
                ->pluck('value', 'key');

            $this->clientId = $deviceId ?: ($settings->get('sync_client_id') ?: $this->clientId);
            if ($settings->get('sync_api_token')) {
                try {
                    $this->apiToken = Crypt::decryptString($settings->get('sync_api_token'));
                } catch (\Throwable) {
                    $this->apiToken = null;
                }
            }
        }

        return $this;
    }

    protected function accountId(): ?string
    {
        return $this->runtimeAccountId
            ?: app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    public function isChild(): bool
    {
        return $this->role === 'child';
    }

    public function hasCloudConfig(): bool
    {
        if (empty($this->cloudUrl) || empty($this->apiToken)) {
            return false;
        }

        return !app()->environment('production')
            || str_starts_with(strtolower((string) $this->cloudUrl), 'https://');
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function push(): array
    {
        if (!$this->hasCloudConfig()) {
            return ['ok' => false, 'message' => 'Cloud sync is not configured.'];
        }

        $accountId = $this->accountId();
        if (!$accountId || !$this->clientId) {
            return ['ok' => false, 'message' => 'No account or device sync context is configured.'];
        }

        $events = null;
        $eventIds = [];

        DB::beginTransaction();
        try {
            $events = DB::table('event_ledger')
                ->where('account_id', $accountId)
                ->where('device_id', $this->clientId)
                ->whereNull('global_sequence')
                ->where(function ($q) {
                    $q->whereNull('synced_at')
                      ->orWhere('sync_status', 'failed')
                      ->orWhere(function ($query) {
                          $query->where('sync_status', 'syncing')
                              ->where('synced_at', '<', now()->subMinutes(5));
                      });
                })
                ->orderBy('local_sequence', 'asc')
                ->lockForUpdate()
                ->limit($this->batchSize)
                ->get();

            if ($events->isEmpty()) {
                DB::commit();
                return ['ok' => true, 'processed' => 0];
            }

            $eventIds = $events->pluck('id')->toArray();

            DB::table('event_ledger')
                ->whereIn('id', $eventIds)
                ->update(['synced_at' => now(), 'sync_status' => 'syncing']);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Sync Push DB Error: " . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        try {
            $response = Http::timeout(30)->withHeaders([
                'X-Sync-Client-Id' => $this->clientId,
                'Authorization' => 'Bearer ' . ($this->apiToken ?? ''),
                'Accept' => 'application/json',
            ])->post("{$this->cloudUrl}/api/v1/sync/receive", [
                'events' => $events->toArray(),
            ]);

            if ($response->successful()) {
                $processed = $response->json('processed');
                $assignments = $response->json('assignments');

                if ($assignments && count($assignments) > 0) {
                    foreach ($assignments as $eventId => $globalSeq) {
                        DB::table('event_ledger')
                            ->where('id', $eventId)
                            ->update([
                                'global_sequence' => $globalSeq,
                                'sync_status' => 'synced',
                            ]);
                    }
                } elseif ($processed > 0 && empty($assignments)) {
                    Log::warning("Sync Push: Cloud processed {$processed} events but returned no assignments. Resetting synced_at.");
                    DB::table('event_ledger')
                        ->whereIn('id', $eventIds)
                        ->update(['synced_at' => null, 'sync_status' => 'pending']);
                }

                Log::info("Sync Push: Processed {$processed} events.");
                return ['ok' => true, 'processed' => (int) $processed];
            } else {
                Log::error("Sync Push Failed: " . $response->body());
                DB::table('event_ledger')
                    ->whereIn('id', $eventIds)
                    ->update(['synced_at' => null, 'sync_status' => 'pending']);
                return ['ok' => false, 'message' => 'Cloud rejected sync push.', 'status' => $response->status()];
            }
        } catch (\Exception $e) {
            Log::error("Sync Push HTTP Error: " . $e->getMessage());
            DB::table('event_ledger')
                ->whereIn('id', $eventIds)
                ->update(['synced_at' => null, 'sync_status' => 'pending']);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function pull(): array
    {
        if (!$this->hasCloudConfig()) {
            return ['ok' => false, 'message' => 'Cloud sync is not configured.'];
        }

        $accountId = $this->accountId();

        if (!$accountId) {
            Log::warning("Sync Pull: No account context.");
            return ['ok' => false, 'message' => 'No account context.'];
        }

        $device = DB::table('devices')->where('device_id', $this->clientId)->where('account_id', $accountId)->first();
        if (!$device) {
            return ['ok' => false, 'message' => 'Configured sync device was not found.'];
        }
        $lastGlobal = (int) (DB::table('sync_cursors')->where('account_id', $accountId)
            ->where('device_id', $this->clientId)->value('last_global_sequence') ?? 0);

        try {
            $response = Http::timeout(30)->withHeaders([
                'X-Sync-Client-Id' => $this->clientId,
                'Authorization' => 'Bearer ' . ($this->apiToken ?? ''),
                'Accept' => 'application/json',
            ])->get("{$this->cloudUrl}/api/v1/sync/serve", [
                'after_global_sequence' => $lastGlobal,
                'limit' => $this->batchSize,
            ]);

            if ($response->successful()) {
                if (is_array($response->json('access_snapshot'))) {
                    app(AccessSnapshotService::class)->import($response->json('access_snapshot'), (string) $accountId);
                }
                $events = $response->json('events');
                if (empty($events)) {
                    return ['ok' => true, 'processed' => 0];
                }

                foreach ($events as $event) {
                    $this->ingestEvent((array) $event);
                }

                $newCursor = collect($events)->max('global_sequence') ?? $lastGlobal;
                DB::table('sync_cursors')->updateOrInsert([
                    'account_id' => $accountId, 'branch_id' => $device->branch_id, 'device_id' => $this->clientId,
                ], ['last_global_sequence' => $newCursor, 'updated_at' => now(), 'created_at' => now()]);

                Log::info("Sync Pull: Ingested " . count($events) . " events.");
                return ['ok' => true, 'processed' => count($events)];
            } else {
                Log::error("Sync Pull Failed: " . $response->body());
                return ['ok' => false, 'message' => 'Cloud rejected sync pull.', 'status' => $response->status()];
            }
        } catch (\Exception $e) {
            Log::error("Sync Pull Error: " . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    protected function ingestEvent(array $eventData): void
    {
        $existing = DB::table('event_ledger')->where('id', $eventData['id'])->first();

        if ($existing) {
            if (is_null($existing->global_sequence)) {
                DB::table('event_ledger')
                    ->where('id', $eventData['id'])
                    ->update([
                        'global_sequence' => $eventData['global_sequence'],
                        'synced_at' => now(),
                    ]);
            }
            if (!DB::table('projected_events')->where('event_id', $eventData['id'])->exists()) {
                app(ProjectionService::class)->projectEvent(
                    DB::table('event_ledger')->where('id', $eventData['id'])->first()
                );
            }
            return;
        }

        $eventPayload = is_string($eventData['event_payload'])
            ? $eventData['event_payload']
            : json_encode($eventData['event_payload']);

        $eventId = $eventData['id'];
        $accountId = $eventData['account_id'] ?? null;
        $deviceId = $eventData['device_id'] ?? 'cloud';

        if (!DB::table('devices')->where('device_id', $deviceId)->exists()) {
            DB::table('devices')->upsert(
                [
                    'device_id' => $deviceId,
                    'account_id' => $accountId,
                    'branch_id' => $eventData['branch_id'] ?? null,
                    'device_name' => 'Synced Device',
                    'trust_status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                'device_id'
            );
        }

        DB::table('event_ledger')->insert([
            'id' => $eventId,
            'account_id' => $accountId,
            'branch_id' => $eventData['branch_id'] ?? null,
            'device_id' => $deviceId,
            'actor_user_id' => $eventData['actor_user_id'] ?? null,
            'event_type' => $eventData['event_type'],
            'event_payload' => $eventPayload,
            'local_sequence' => $eventData['local_sequence'],
            'event_time_utc' => $eventData['event_time_utc'] ?? now(),
            'event_hash' => $eventData['event_hash'],
            'previous_hash' => $eventData['previous_hash'] ?? null,
            'global_sequence' => $eventData['global_sequence'],
            'synced_at' => now(),
            'received_at_cloud' => $eventData['received_at_cloud'] ?? now(),
        ]);

        // Replay the event into local read models. Projection exceptions must
        // escape so the pull cursor is not advanced beyond a failed event.
        if ($accountId) {
            $event = (object) [
                'id' => $eventId,
                'account_id' => $accountId,
                'branch_id' => $eventData['branch_id'] ?? null,
                'event_type' => $eventData['event_type'],
                'event_payload' => json_decode($eventPayload, true),
                'event_time_utc' => $eventData['event_time_utc'] ?? now(),
            ];
            app(ProjectionService::class)->projectEvent($event);
        }
    }

    public function restoreFromCloud(): array
    {
        if (!$this->hasCloudConfig()) {
            return ['ok' => false, 'message' => 'Cloud sync is not configured.'];
        }

        $accountId = $this->accountId();
        if (!$accountId) {
            Log::error("Sync Restore: No account context.");
            return ['ok' => false, 'message' => 'No account context.'];
        }

        try {
            $response = Http::timeout(60)->withHeaders([
                'X-Sync-Client-Id' => $this->clientId,
                'Authorization' => 'Bearer ' . ($this->apiToken ?? ''),
                'Accept' => 'application/json',
            ])->get("{$this->cloudUrl}/api/v1/sync/full-restore");

            if (!$response->successful()) {
                Log::error("Sync Restore Failed: " . $response->body());
                return ['ok' => false, 'message' => 'Cloud rejected restore.', 'status' => $response->status()];
            }

            $events = $response->json('events');
            if (is_array($response->json('access_snapshot'))) {
                app(AccessSnapshotService::class)->import($response->json('access_snapshot'), (string) $accountId);
            }
            if (empty($events)) {
                Log::info("Sync Restore: No events to restore.");
                return ['ok' => true, 'processed' => 0];
            }

            DB::transaction(function () use ($accountId, $events) {
                app(ProjectionService::class)->resetReadModels($accountId, $events);

                foreach ($events as $event) {
                    $existing = DB::table('event_ledger')->where('id', $event['id'])->first();
                    if (!$existing) {
                        $this->ingestEvent((array) $event);
                        continue;
                    }
                    app(ProjectionService::class)->projectEvent($existing);
                }
            });

            Log::info("Sync Restore: Restored " . count($events) . " events for account {$accountId}.");
            return ['ok' => true, 'processed' => count($events)];
        } catch (\Exception $e) {
            Log::error("Sync Restore Error: " . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
