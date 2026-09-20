<?php

namespace Tests\Feature\Sync;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Device;
use App\Models\EventLedger;
use App\Models\Sale;
use App\Models\User;
use App\Jobs\ProcessLedgerEventJob;
use App\Jobs\SyncEventsToCloudJob;
use App\Services\EventLedgerService;
use App\Services\DomainEventService;
use App\Services\LedgerIntegrityService;
use App\Services\ProjectionService;
use App\Services\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected string $accountId;
    protected string $deviceId;
    protected string $branchId;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $account = \AbacPermissions\Models\Account::create([
            'name' => 'Sync Test Account',
            'slug' => 'sync-test-account',
        ]);

        $this->accountId = (string) $account->id;

        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) {
            $mock->shouldReceive('getAccountId')->andReturn($this->accountId);
            $mock->shouldReceive('setAccount')->zeroOrMoreTimes();
        });

        $this->user = User::factory()->create();
        $this->user->accounts()->attach($account);

        $this->branchId = (string) Str::ulid();
        Branch::create([
            'branch_id' => $this->branchId,
            'account_id' => $this->accountId,
            'name' => 'Sync Test Branch',
            'code' => 'SYNC',
            'is_active' => true,
        ]);

        $this->deviceId = (string) Str::ulid();
        Device::create([
            'device_id' => $this->deviceId,
            'account_id' => $this->accountId,
            'branch_id' => $this->branchId,
            'device_name' => 'Test Device',
            'trust_status' => 'active',
            'sync_token_hash' => hash('sha256', 'test-token'),
        ]);

        config([
            'sync.role' => 'child',
            'sync.client_id' => $this->deviceId,
            'sync.api_token' => 'test-token',
            'sync.cloud_url' => 'http://localhost',
            'sync.batch_size' => 100,
            'sync.queue_connection' => 'database',
        ]);

        $this->withHeader('X-Sync-Client-Id', $this->deviceId);
    }

    // -----------------------------------------------------------------------
    //  1. EventLedgerService — Event Emission
    // -----------------------------------------------------------------------

    #[Test]
    public function it_emits_event_with_hash_chain_and_increments_sequence(): void
    {
        Http::fake();

        $service = app(EventLedgerService::class);

        $result1 = $service->emitEvent([
            'account_id' => $this->accountId,
            'device_id' => $this->deviceId,
            'actor_user_id' => $this->user->id,
            'event_type' => 'SALE_FINALIZED',
            'event_payload' => ['sale_id' => (string) Str::ulid(), 'total_amount' => 100, 'tax_amount' => 10, 'payment_type' => 'cash'],
        ]);

        $result2 = $service->emitEvent([
            'account_id' => $this->accountId,
            'device_id' => $this->deviceId,
            'actor_user_id' => $this->user->id,
            'event_type' => 'STOCK_ADJUSTED',
            'event_payload' => ['batch_id' => (string) Str::ulid(), 'change' => -5],
        ]);

        $event1 = EventLedger::withoutGlobalScopes()->find($result1->id);
        $event2 = EventLedger::withoutGlobalScopes()->find($result2->id);

        $this->assertNotNull($event1);
        $this->assertNotNull($event2);
        $this->assertEquals(1, $event1->local_sequence);
        $this->assertEquals(2, $event2->local_sequence);
        $this->assertEquals($this->accountId, (string) $event1->account_id);
        $this->assertEquals($this->deviceId, $event1->device_id);

        $this->assertEquals(str_repeat('0', 64), $event1->previous_hash);
        $this->assertEquals($event1->event_hash, $event2->previous_hash);
        $this->assertTrue(app(LedgerIntegrityService::class)->verifyHashChain($this->accountId));
    }

    #[Test]
    public function it_dispatches_jobs_after_emitting_event(): void
    {
        Queue::fake();
        Http::fake();

        $service = app(EventLedgerService::class);
        $service->emitEvent([
            'account_id' => $this->accountId,
            'device_id' => $this->deviceId,
            'actor_user_id' => $this->user->id,
            'event_type' => 'SALE_FINALIZED',
            'event_payload' => ['total_amount' => 100],
        ]);

        Queue::assertPushed(ProcessLedgerEventJob::class);
        Queue::assertPushed(SyncEventsToCloudJob::class);
    }

    #[Test]
    public function it_projects_sale_from_emitted_event(): void
    {
        Http::fake();

        $saleId = (string) Str::ulid();
        $service = app(EventLedgerService::class);
        $service->emitEvent([
            'account_id' => $this->accountId,
            'device_id' => $this->deviceId,
            'actor_user_id' => $this->user->id,
            'event_type' => 'SALE_FINALIZED',
            'event_payload' => [
                'sale_id' => $saleId,
                'total_amount' => 150.00,
                'tax_amount' => 15.00,
                'payment_type' => 'cash',
            ],
        ]);

        $sale = Sale::withoutGlobalScopes()
            ->where('account_id', $this->accountId)
            ->where('id', $saleId)
            ->first();

        $this->assertNotNull($sale);
        $this->assertEquals(150.00, (float) $sale->total_amount);
        $this->assertEquals(15.00, (float) $sale->tax_amount);
        $this->assertEquals('cash', $sale->payment_type);
    }

    // -----------------------------------------------------------------------
    //  2. SyncService — Push (Child → Cloud)
    // -----------------------------------------------------------------------

    #[Test]
    public function push_sends_unsynced_events_and_updates_global_sequence(): void
    {
        $eventId1 = (string) Str::ulid();
        $eventId2 = (string) Str::ulid();

        $this->seedEvent($eventId1, 1);
        $this->seedEvent($eventId2, 2);

        Http::fake([
            'http://localhost/*' => Http::response([
                'processed' => 2,
                'assignments' => [
                    $eventId1 => 1001,
                    $eventId2 => 1002,
                ],
            ], 200),
        ]);

        app(SyncService::class)->push();

        $event1 = DB::table('event_ledger')->where('id', $eventId1)->first();
        $event2 = DB::table('event_ledger')->where('id', $eventId2)->first();

        $this->assertEquals(1001, $event1->global_sequence);
        $this->assertEquals('synced', $event1->sync_status);
        $this->assertNotNull($event1->synced_at);

        $this->assertEquals(1002, $event2->global_sequence);
        $this->assertEquals('synced', $event2->sync_status);
        $this->assertNotNull($event2->synced_at);
    }

    #[Test]
    public function push_resets_synced_at_on_http_failure(): void
    {
        $eventId = (string) Str::ulid();
        $this->seedEvent($eventId, 1);

        Http::fake([
            'http://localhost/*' => Http::response('Server Error', 500),
        ]);

        app(SyncService::class)->push();

        $event = DB::table('event_ledger')->where('id', $eventId)->first();
        $this->assertNull($event->global_sequence);
        $this->assertNull($event->synced_at);
        $this->assertEquals('pending', $event->sync_status);
    }

    #[Test]
    public function push_skips_when_cloud_config_missing(): void
    {
        $eventId = (string) Str::ulid();
        $this->seedEvent($eventId, 1);

        config(['sync.cloud_url' => '', 'sync.api_token' => '']);

        Http::fake();

        app(SyncService::class)->push();

        Http::assertNothingSent();

        $event = DB::table('event_ledger')->where('id', $eventId)->first();
        $this->assertNull($event->global_sequence);
    }

    #[Test]
    public function push_skips_when_no_unsynced_events(): void
    {
        Http::fake();

        app(SyncService::class)->push();

        Http::assertNothingSent();
    }

    #[Test]
    public function push_only_sends_batch_size_events(): void
    {
        config(['sync.batch_size' => 2]);

        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $id = (string) Str::ulid();
            $ids[] = $id;
            $this->seedEvent($id, $i);
        }

        $requestCount = 0;
        Http::fake(function ($request) use (&$requestCount) {
            $requestCount++;
            return Http::response([
                'processed' => 2,
                'assignments' => [],
            ], 200);
        });

        app(SyncService::class)->push();

        $this->assertEquals(1, $requestCount);

        $sentIds = json_decode(Http::recorded()[0][0]->body(), true);
        $this->assertCount(2, $sentIds['events']);
    }

    // -----------------------------------------------------------------------
    //  3. SyncService — Pull (Cloud → Child)
    // -----------------------------------------------------------------------

    #[Test]
    public function pull_ingests_new_events_from_cloud(): void
    {
        $cloudEventId = (string) Str::ulid();
        $cloudEvents = [
            [
                'id' => $cloudEventId,
                'account_id' => $this->accountId,
                'device_id' => 'cloud',
                'actor_user_id' => null,
                'event_type' => 'SALE_FINALIZED',
                'event_payload' => json_encode([
                    'sale_id' => (string) Str::ulid(),
                    'total_amount' => 200,
                    'tax_amount' => 20,
                    'payment_type' => 'card',
                ]),
                'local_sequence' => 0,
                'global_sequence' => 5001,
                'event_time_utc' => now()->toISOString(),
                'event_hash' => str_repeat('a', 64),
                'received_at_cloud' => now()->toISOString(),
            ],
        ];

        Http::fake([
            'http://localhost/*' => Http::response(['events' => $cloudEvents], 200),
        ]);

        app(SyncService::class)->pull();

        $this->assertDatabaseHas('event_ledger', [
            'id' => $cloudEventId,
            'global_sequence' => 5001,
            'device_id' => 'cloud',
        ]);
    }

    #[Test]
    public function pull_projects_events_during_ingest(): void
    {
        $saleId = (string) Str::ulid();
        $cloudEventId = (string) Str::ulid();
        $cloudEvents = [
            [
                'id' => $cloudEventId,
                'account_id' => $this->accountId,
                'device_id' => 'cloud',
                'actor_user_id' => null,
                'event_type' => 'SALE_FINALIZED',
                'event_payload' => json_encode([
                    'sale_id' => $saleId,
                    'total_amount' => 300,
                    'tax_amount' => 30,
                    'payment_type' => 'cash',
                ]),
                'local_sequence' => 0,
                'global_sequence' => 6001,
                'event_time_utc' => now()->toISOString(),
                'event_hash' => str_repeat('b', 64),
                'received_at_cloud' => now()->toISOString(),
            ],
        ];

        Http::fake([
            'http://localhost/*' => Http::response(['events' => $cloudEvents], 200),
        ]);

        app(SyncService::class)->pull();

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'total_amount' => 300.00,
        ]);
    }

    #[Test]
    public function pull_skips_already_ingested_events(): void
    {
        $eventId = (string) Str::ulid();
        $this->seedEvent($eventId, 1, ['global_sequence' => 7001]);

        $cloudEvents = [
            [
                'id' => $eventId,
                'account_id' => $this->accountId,
                'device_id' => $this->deviceId,
                'actor_user_id' => null,
                'event_type' => 'SALE_FINALIZED',
                'event_payload' => json_encode(['total_amount' => 400]),
                'local_sequence' => 1,
                'global_sequence' => 7001,
                'event_time_utc' => now()->toISOString(),
                'event_hash' => str_repeat('c', 64),
                'received_at_cloud' => now()->toISOString(),
            ],
        ];

        Http::fake([
            'http://localhost/*' => Http::response(['events' => $cloudEvents], 200),
        ]);

        app(SyncService::class)->pull();

        // Only one record should exist with this ID
        $this->assertEquals(1, DB::table('event_ledger')->where('id', $eventId)->count());
    }

    // -----------------------------------------------------------------------
    //  4. SyncService — Restore From Cloud
    // -----------------------------------------------------------------------

    #[Test]
    public function restore_wipes_and_replays_read_models(): void
    {
        $saleId = (string) Str::ulid();

        // Seed a local sale that should be wiped
        Sale::withoutGlobalScopes()->create([
            'id' => $saleId,
            'account_id' => $this->accountId,
            'total_amount' => 999,
            'tax_amount' => 0,
            'payment_type' => 'cash',
            'finalized_at' => now(),
        ]);

        $this->assertDatabaseHas('sales', ['id' => $saleId]);

        $newSaleId = (string) Str::ulid();
        $restoreEvents = [
            [
                'id' => (string) Str::ulid(),
                'account_id' => $this->accountId,
                'device_id' => 'cloud',
                'actor_user_id' => null,
                'event_type' => 'SALE_FINALIZED',
                'event_payload' => json_encode([
                    'sale_id' => $newSaleId,
                    'total_amount' => 500,
                    'tax_amount' => 50,
                    'payment_type' => 'card',
                ]),
                'local_sequence' => 0,
                'global_sequence' => 8001,
                'event_time_utc' => now()->toISOString(),
                'event_hash' => str_repeat('d', 64),
                'received_at_cloud' => now()->toISOString(),
            ],
        ];

        Http::fake([
            'http://localhost/*' => Http::response(['events' => $restoreEvents], 200),
        ]);

        app(SyncService::class)->restoreFromCloud();

        // Old sale should be gone
        $this->assertDatabaseMissing('sales', ['id' => $saleId]);

        // New sale should be projected from restored events
        $this->assertDatabaseHas('sales', [
            'id' => $newSaleId,
            'total_amount' => 500.00,
        ]);
    }

    #[Test]
    public function restore_does_nothing_when_cloud_returns_empty(): void
    {
        $saleId = (string) Str::ulid();
        Sale::withoutGlobalScopes()->create([
            'id' => $saleId,
            'account_id' => $this->accountId,
            'total_amount' => 999,
            'tax_amount' => 0,
            'payment_type' => 'cash',
            'finalized_at' => now(),
        ]);

        Http::fake([
            'http://localhost/*' => Http::response(['events' => []], 200),
        ]);

        app(SyncService::class)->restoreFromCloud();

        // Sale should NOT be wiped when there are no events to restore
        $this->assertDatabaseHas('sales', ['id' => $saleId]);
    }

    // -----------------------------------------------------------------------
    //  5. CloudSyncController — Cloud-side Endpoints
    // -----------------------------------------------------------------------

    #[Test]
    public function cloud_receive_batch_assigns_global_sequence(): void
    {
        config(['sync.api_token' => 'test-token']);

        $device = Device::create([
            'device_id' => 'branch-device-1',
            'device_name' => 'Branch 1',
            'trust_status' => 'active',
        ]);

        $eventId = (string) Str::ulid();
        $payload = ['events' => [$this->cloudEvent($eventId, [
            'sale_id' => (string) Str::ulid(),
            'subtotal_amount' => 100,
            'total_amount' => 100,
            'tax_amount' => 0,
            'payment_type' => 'cash',
        ])]];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/sync/receive', $payload);

        $response->assertOk();
        $response->assertJsonStructure(['processed', 'assignments']);

        $this->assertEquals(1, $response->json('processed'));
        $this->assertNotNull($response->json('assignments.' . $eventId));

        $this->assertDatabaseHas('event_ledger', [
            'id' => $eventId,
            'global_sequence' => $response->json('assignments.' . $eventId),
        ]);
    }

    #[Test]
    public function cloud_receive_deduplicates_events_by_id(): void
    {
        config(['sync.api_token' => 'test-token']);

        $device = Device::create([
            'device_id' => 'branch-device-2',
            'device_name' => 'Branch 2',
            'trust_status' => 'active',
        ]);

        $eventId = (string) Str::ulid();

        $payload = ['events' => [$this->cloudEvent($eventId, [
            'sale_id' => (string) Str::ulid(),
            'subtotal_amount' => 100,
            'total_amount' => 100,
            'tax_amount' => 0,
            'payment_type' => 'cash',
        ])]];

        // Send same event twice
        $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/sync/receive', $payload);

        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/sync/receive', $payload);

        $response2->assertOk();
        $this->assertEquals(0, $response2->json('processed'));

        $this->assertEquals(1, DB::table('event_ledger')->where('id', $eventId)->count());
    }

    #[Test]
    public function cloud_serve_returns_events_after_global_sequence(): void
    {
        config(['sync.api_token' => 'test-token']);

        $device = Device::create([
            'device_id' => 'branch-device-3',
            'device_name' => 'Branch 3',
            'trust_status' => 'active',
        ]);

        DB::table('event_ledger')->insert([
            'id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'device_id' => 'branch-device-3',
            'event_type' => 'SALE_FINALIZED',
            'event_payload' => json_encode(['total_amount' => 100]),
            'local_sequence' => 1,
            'global_sequence' => 1,
            'event_time_utc' => now(),
            'event_hash' => str_repeat('g', 64),
            'sync_status' => 'synced',
            'synced_at' => now(),
        ]);

        DB::table('event_ledger')->insert([
            'id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'device_id' => 'branch-device-3',
            'event_type' => 'STOCK_ADJUSTED',
            'event_payload' => json_encode(['change' => -5]),
            'local_sequence' => 2,
            'global_sequence' => 2,
            'event_time_utc' => now(),
            'event_hash' => str_repeat('h', 64),
            'sync_status' => 'synced',
            'synced_at' => now(),
        ]);

        // Should only return events with global_sequence > 1
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/sync/serve?' . http_build_query([
            'after_global_sequence' => 1,
            'account_id' => $this->accountId,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'events');
        $this->assertEquals(2, $response->json('events.0.global_sequence'));
    }

    #[Test]
    public function cloud_serve_respects_tenant_isolation(): void
    {
        config(['sync.api_token' => 'test-token']);

        $device = Device::create([
            'device_id' => 'branch-device-4',
            'device_name' => 'Branch 4',
            'trust_status' => 'active',
        ]);

        $otherAccount = \AbacPermissions\Models\Account::create([
            'name' => 'Other Account',
            'slug' => 'other-account',
        ]);

        DB::table('event_ledger')->insert([
            'id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'device_id' => 'branch-device-4',
            'event_type' => 'SALE_FINALIZED',
            'event_payload' => json_encode(['total_amount' => 100]),
            'local_sequence' => 2,
            'global_sequence' => 10,
            'event_time_utc' => now(),
            'event_hash' => str_repeat('i', 64),
            'sync_status' => 'synced',
            'synced_at' => now(),
        ]);

        DB::table('event_ledger')->insert([
            'id' => (string) Str::ulid(),
            'account_id' => (string) $otherAccount->id,
            'device_id' => 'branch-device-4',
            'event_type' => 'SALE_FINALIZED',
            'event_payload' => json_encode(['total_amount' => 999]),
            'local_sequence' => 1,
            'global_sequence' => 11,
            'event_time_utc' => now(),
            'event_hash' => str_repeat('j', 64),
            'sync_status' => 'synced',
            'synced_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/sync/serve?' . http_build_query([
            'after_global_sequence' => 0,
            'account_id' => $this->accountId,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'events');
        $this->assertEquals($this->accountId, (string) $response->json('events.0.account_id'));
    }

    #[Test]
    public function cloud_full_restore_returns_all_events_for_account(): void
    {
        config(['sync.api_token' => 'test-token']);

        Device::create([
            'device_id' => 'cloud',
            'device_name' => 'Cloud Sync',
            'trust_status' => 'active',
        ]);

        $eventId = (string) Str::ulid();
        DB::table('event_ledger')->insert([
            'id' => $eventId,
            'account_id' => $this->accountId,
            'device_id' => 'cloud',
            'event_type' => 'SALE_FINALIZED',
            'event_payload' => json_encode(['total_amount' => 200]),
            'local_sequence' => 0,
            'global_sequence' => 100,
            'event_time_utc' => now(),
            'event_hash' => str_repeat('k', 64),
            'sync_status' => 'synced',
            'synced_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/sync/full-restore?' . http_build_query([
            'account_id' => $this->accountId,
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'events');
        $this->assertEquals($eventId, $response->json('events.0.id'));
    }

    #[Test]
    public function cloud_sync_endpoints_reject_invalid_token(): void
    {
        config(['sync.api_token' => 'real-token']);

        $this->postJson('/api/v1/sync/receive', ['events' => []])
            ->assertUnauthorized();

        $this->getJson('/api/v1/sync/serve')
            ->assertUnauthorized();

        $this->getJson('/api/v1/sync/full-restore')
            ->assertUnauthorized();
    }

    #[Test]
    public function cloud_serve_includes_offline_access_snapshot(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/sync/serve?after_global_sequence=0');

        $response->assertOk()
            ->assertJsonPath('access_snapshot.account_id', $this->accountId)
            ->assertJsonPath('access_snapshot.users.0.id', $this->user->id);
        $this->assertNotEmpty($response->json('access_snapshot.users.0.password'));
    }

    #[Test]
    public function pull_refreshes_offline_users_even_when_there_are_no_events(): void
    {
        $offlineUserId = (string) Str::ulid();
        Http::fake([
            'http://localhost/*' => Http::response([
                'events' => [],
                'access_snapshot' => [
                    'account_id' => $this->accountId,
                    'users' => [[
                        'id' => $offlineUserId,
                        'name' => 'Offline Cashier',
                        'email' => 'offline.cashier@example.test',
                        'password' => password_hash('secret', PASSWORD_BCRYPT),
                        'is_active' => true,
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ]],
                    'memberships' => [['user_id' => $offlineUserId, 'account_id' => $this->accountId]],
                    'roles' => [], 'role_users' => [], 'permissions' => [], 'assigned_permissions' => [],
                ],
            ], 200),
        ]);

        $result = app(SyncService::class)->pull();

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('users', ['id' => $offlineUserId, 'is_active' => true]);
        $this->assertDatabaseHas('account_user', ['user_id' => $offlineUserId, 'account_id' => $this->accountId]);
    }

    #[Test]
    public function parent_authority_uses_a_distinct_hash_chain_for_each_branch(): void
    {
        config(['sync.role' => 'parent']);
        $otherBranch = Branch::create([
            'branch_id' => (string) Str::ulid(), 'account_id' => $this->accountId,
            'name' => 'Second Branch', 'code' => 'SECOND', 'is_active' => true,
        ]);

        app(DomainEventService::class)->record('SETTINGS_UPDATED', ['settings' => []], $this->user->id, $this->branchId);
        app(DomainEventService::class)->record('SETTINGS_UPDATED', ['settings' => []], $this->user->id, $otherBranch->branch_id);

        $events = EventLedger::withoutGlobalScopes()->orderBy('global_sequence')->get();
        $this->assertCount(2, $events);
        $this->assertNotSame($events[0]->device_id, $events[1]->device_id);
        $this->assertSame($this->branchId, $events[0]->branch_id);
        $this->assertSame($otherBranch->branch_id, $events[1]->branch_id);
    }

    // -----------------------------------------------------------------------
    //  6. Push — Edge Cases (empty assignments, partial failure)
    // -----------------------------------------------------------------------

    #[Test]
    public function push_resets_synced_at_when_cloud_returns_empty_assignments(): void
    {
        $eventId = (string) Str::ulid();
        $this->seedEvent($eventId, 1);

        Http::fake([
            'http://localhost/*' => Http::response([
                'processed' => 1,
                'assignments' => [],
            ], 200),
        ]);

        app(SyncService::class)->push();

        $event = DB::table('event_ledger')->where('id', $eventId)->first();
        $this->assertNull($event->synced_at);
        $this->assertNull($event->global_sequence);
        $this->assertEquals('pending', $event->sync_status);
    }

    #[Test]
    public function push_resets_synced_at_when_cloud_returns_null_assignments(): void
    {
        $eventId = (string) Str::ulid();
        $this->seedEvent($eventId, 1);

        Http::fake([
            'http://localhost/*' => Http::response([
                'processed' => 1,
                'assignments' => null,
            ], 200),
        ]);

        app(SyncService::class)->push();

        $event = DB::table('event_ledger')->where('id', $eventId)->first();
        $this->assertNull($event->synced_at);
        $this->assertNull($event->global_sequence);
    }

    // -----------------------------------------------------------------------
    //  7. Pull — Edge Cases (missing account_id, empty response)
    // -----------------------------------------------------------------------

    #[Test]
    public function push_retries_failed_events(): void
    {
        $eventId = (string) Str::ulid();
        $this->seedEvent($eventId, 1, [
            'synced_at' => now(),
            'sync_status' => 'failed',
        ]);

        Http::fake([
            'http://localhost/*' => Http::response([
                'processed' => 1,
                'assignments' => [$eventId => 2001],
            ], 200),
        ]);

        app(SyncService::class)->push();

        $event = DB::table('event_ledger')->where('id', $eventId)->first();
        $this->assertEquals(2001, $event->global_sequence);
        $this->assertEquals('synced', $event->sync_status);
    }

    #[Test]
    public function push_skips_when_only_url_missing(): void
    {
        $eventId = (string) Str::ulid();
        $this->seedEvent($eventId, 1);

        config(['sync.cloud_url' => '']);

        Http::fake();

        app(SyncService::class)->push();

        Http::assertNothingSent();
    }

    #[Test]
    public function push_skips_when_only_token_missing(): void
    {
        $eventId = (string) Str::ulid();
        $this->seedEvent($eventId, 1);

        config(['sync.api_token' => '']);

        Http::fake();

        app(SyncService::class)->push();

        Http::assertNothingSent();
    }

    #[Test]
    public function pull_skips_when_no_account_context(): void
    {
        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) {
            $mock->shouldReceive('getAccountId')->andReturn(null);
        });

        Http::fake();

        app(SyncService::class)->pull();

        Http::assertNothingSent();
    }

    // -----------------------------------------------------------------------
    //  8. Restore — Wipe Additional Read Models
    // -----------------------------------------------------------------------

    #[Test]
    public function restore_wipes_batches_and_returns_and_customer_sales(): void
    {
        $drugId = (string) Str::ulid();
        $batchId = (string) Str::ulid();
        $saleId = (string) Str::ulid();
        $eventId = (string) Str::ulid();

        DB::table('drugs')->insert(['id' => $drugId, 'name' => 'Test Drug']);
        Sale::withoutGlobalScopes()->create([
            'id' => $saleId,
            'account_id' => $this->accountId,
            'total_amount' => 100,
            'tax_amount' => 10,
            'payment_type' => 'cash',
            'finalized_at' => now(),
        ]);
        DB::table('customer_sales')->insert([
            'id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'customer_id' => (string) Str::ulid(),
            'sale_id' => $saleId,
        ]);
        DB::table('returns')->insert([
            'id' => (string) Str::ulid(),
            'sale_id' => $saleId,
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'refund_amount' => 10,
            'returned_at' => now(),
        ]);
        Batch::create([
            'id' => $batchId,
            'drug_id' => $drugId,
            'expiry_date' => now()->addYear(),
            'quantity' => 100,
        ]);

        $restoreEvents = [
            [
                'id' => $eventId,
                'account_id' => $this->accountId,
                'device_id' => 'cloud',
                'actor_user_id' => null,
                'event_type' => 'BATCH_REGISTERED',
                'event_payload' => json_encode([
                    'batch_id' => $batchId,
                    'drug_id' => $drugId,
                    'initial_quantity' => 50,
                    'selling_price' => 25,
                    'cost_price' => 15,
                ]),
                'local_sequence' => 0,
                'global_sequence' => 9001,
                'event_time_utc' => now()->toISOString(),
                'event_hash' => str_repeat('m', 64),
                'received_at_cloud' => now()->toISOString(),
            ],
        ];

        Http::fake([
            'http://localhost/*' => Http::response(['events' => $restoreEvents], 200),
        ]);

        app(SyncService::class)->restoreFromCloud();

        $this->assertDatabaseMissing('customer_sales', ['sale_id' => $saleId]);
        $this->assertDatabaseMissing('returns', ['sale_id' => $saleId]);
        $this->assertDatabaseMissing('sales', ['id' => $saleId]);

        $batch = Batch::find($batchId);
        $this->assertNotNull($batch);
        $this->assertEquals(50, $batch->quantity);
    }

    #[Test]
    public function restore_replays_batch_registered_without_duplicate_key_error(): void
    {
        $drugId = (string) Str::ulid();
        $batchId = (string) Str::ulid();
        $eventId = (string) Str::ulid();

        DB::table('drugs')->insert(['id' => $drugId, 'name' => 'Test Drug']);
        Batch::create([
            'id' => $batchId,
            'drug_id' => $drugId,
            'expiry_date' => now()->addYear(),
            'quantity' => 999,
        ]);

        $restoreEvents = [
            [
                'id' => $eventId,
                'account_id' => $this->accountId,
                'device_id' => 'cloud',
                'actor_user_id' => null,
                'event_type' => 'BATCH_REGISTERED',
                'event_payload' => json_encode([
                    'batch_id' => $batchId,
                    'drug_id' => $drugId,
                    'initial_quantity' => 75,
                    'selling_price' => 30,
                    'cost_price' => 18,
                ]),
                'local_sequence' => 0,
                'global_sequence' => 9002,
                'event_time_utc' => now()->toISOString(),
                'event_hash' => str_repeat('n', 64),
                'received_at_cloud' => now()->toISOString(),
            ],
        ];

        Http::fake([
            'http://localhost/*' => Http::response(['events' => $restoreEvents], 200),
        ]);

        app(SyncService::class)->restoreFromCloud();

        $batch = Batch::find($batchId);
        $this->assertNotNull($batch);
        $this->assertEquals(75, $batch->quantity);
        $this->assertDatabaseHas('inventory', [
            'batch_id' => $batchId,
            'quantity_on_hand' => 75,
        ]);
    }

    // -----------------------------------------------------------------------
    //  9. CloudSyncController — Validation & Device Auto-Creation
    // -----------------------------------------------------------------------

    #[Test]
    public function cloud_receive_rejects_unknown_device(): void
    {
        config(['sync.api_token' => 'test-token']);

        $unknownDeviceId = (string) Str::ulid();
        $eventId = (string) Str::ulid();
        $payload = [
            'events' => [
                [
                    'id' => $eventId,
                    'account_id' => $this->accountId,
                    'branch_id' => $this->branchId,
                    'device_id' => $unknownDeviceId,
                    'actor_user_id' => null,
                    'event_type' => 'SALE_FINALIZED',
                    'event_payload' => json_encode(['total_amount' => 100]),
                    'local_sequence' => 1,
                    'event_time_utc' => now()->toISOString(),
                    'previous_hash' => str_repeat('0', 64),
                    'event_hash' => str_repeat('o', 64),
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/sync/receive', $payload);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('devices', ['device_id' => $unknownDeviceId]);
        $this->assertDatabaseMissing('event_ledger', ['id' => $eventId]);
    }

    #[Test]
    public function cloud_receive_validates_required_fields(): void
    {
        config(['sync.api_token' => 'test-token']);

        $payload = [
            'events' => [
                [
                    'id' => (string) Str::ulid(),
                    'account_id' => $this->accountId,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/sync/receive', $payload);

        $response->assertStatus(422);
    }

    #[Test]
    public function cloud_receive_rejects_unknown_account_id(): void
    {
        config(['sync.api_token' => 'test-token']);

        $eventId = (string) Str::ulid();
        $payload = [
            'events' => [
                [
                    'id' => $eventId,
                    'account_id' => 'non-existent-account',
                    'branch_id' => $this->branchId,
                    'device_id' => (string) Str::ulid(),
                    'event_type' => 'SALE_FINALIZED',
                    'event_payload' => json_encode(['total_amount' => 100]),
                    'local_sequence' => 1,
                    'event_time_utc' => now()->toISOString(),
                    'previous_hash' => str_repeat('0', 64),
                    'event_hash' => str_repeat('p', 64),
                ],
            ],
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
            'Accept' => 'application/json',
        ])->postJson('/api/v1/sync/receive', $payload);

        $response->assertUnprocessable();
        $this->assertDatabaseMissing('event_ledger', ['id' => $eventId]);
    }

    // -----------------------------------------------------------------------
    //  SyncEventsToCloudJob — Retry & Failure Logging
    // -----------------------------------------------------------------------

    #[Test]
    public function sync_job_has_retry_configuration(): void
    {
        $job = new SyncEventsToCloudJob();

        $this->assertEquals(5, $job->tries);
        $this->assertEquals(3, $job->maxExceptions);
        $this->assertEquals([10, 30, 60, 120, 300], $job->backoff());
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    protected function seedEvent(string $id, int $localSequence, array $overrides = []): void
    {
        DB::table('event_ledger')->insert(array_merge([
            'id' => $id,
            'account_id' => $this->accountId,
            'device_id' => $this->deviceId,
            'actor_user_id' => $this->user->id,
            'event_type' => 'SALE_FINALIZED',
            'event_payload' => json_encode(['sale_id' => (string) Str::ulid(), 'total_amount' => 100, 'tax_amount' => 10, 'payment_type' => 'cash']),
            'local_sequence' => $localSequence,
            'global_sequence' => null,
            'event_time_utc' => now(),
            'event_hash' => str_repeat('0', 64),
            'sync_status' => 'pending',
            'synced_at' => null,
        ], $overrides));
    }

    protected function cloudEvent(string $id, array $payload): array
    {
        $eventTime = now()->startOfSecond()->toISOString();
        $event = [
            'id' => $id,
            'account_id' => $this->accountId,
            'branch_id' => $this->branchId,
            'device_id' => $this->deviceId,
            'actor_user_id' => $this->user->id,
            'event_type' => 'SALE_FINALIZED',
            'event_version' => 1,
            'event_payload' => $payload,
            'local_sequence' => 1,
            'event_time_utc' => $eventTime,
            'previous_hash' => str_repeat('0', 64),
        ];
        $event['event_hash'] = app(EventLedgerService::class)->computeEventHash($event);

        return $event;
    }
}
