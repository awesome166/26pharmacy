<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Inventory;
use App\Models\Drug;
use App\Services\EventLedgerService;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $returnService;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock Ledger to avoid full event heavy lifting if needed, or use real one
        // Using real one for integration simplicity as it's just DB transactions
        $this->returnService = app(ReturnService::class);
    }

    #[Test]
    public function it_processes_return_and_logs_audit()
    {
        // Setup Account/User Context
        $user = User::factory()->create();
        $this->actingAs($user);

        $account = \AbacPermissions\Models\Account::create(['name' => 'Test', 'slug' => 'test']);
        config(['sync.role' => 'child', 'sync.queue_connection' => 'database']);
        $branchId = (string) \Illuminate\Support\Str::ulid();
        \App\Models\Branch::create([
            'branch_id' => $branchId, 'account_id' => $account->id,
            'name' => 'Main Branch', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) use ($account) {
            $mock->shouldReceive('getAccountId')->andReturn($account->id);
        });

        // Setup Inventory & Sale
        $drug = Drug::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'account_id' => $account->id,
            'name' => 'Test Drug',
        ]);

        $batch = \App\Models\Batch::create([
             'id' => \Illuminate\Support\Str::ulid(),
             'account_id' => $account->id,
             'branch_id' => $branchId,
             'batch_number' => 'BATCH-001',
             'drug_id' => $drug->id,
             'manufacturer' => 'Test Pharma',
             'expiry_date' => now()->addYear(),
        ]);

        $inventory = Inventory::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'account_id' => $account->id,
            'branch_id' => $branchId,
            'drug_id' => $drug->id,
            'batch_id' => $batch->id,
            'quantity_on_hand' => 10,
            'selling_price' => 10.00,
            'cost_price' => 5.00
        ]);

        $sale = Sale::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'account_id' => $account->id,
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'total_amount' => 20.00,
            'payment_type' => 'cash',
        ]);

        $saleItem = SaleItem::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'sale_id' => $sale->id,
            'inventory_id' => $inventory->id,
            'batch_id' => $batch->id,
            'drug_id' => $drug->id,
            'quantity' => 2,
            'price' => 10.00,
            'line_total' => 20.00,
            'tax_amount' => 0.00,
        ]);

        $deviceId = (string) \Illuminate\Support\Str::ulid();
        config(['sync.client_id' => $deviceId]);
        \App\Models\Device::create([
             'device_id' => $deviceId,
             'account_id' => $account->id,
             'branch_id' => $branchId,
             'device_name' => 'Test Device',
             'trust_status' => 'active',
        ]);

        // Perform Return with Restock
        $returnData = [
            'sale_id' => $sale->id,
            'refund_amount' => 10.00,
            'refund_method' => 'cash',
            'reason' => 'Defective',
            'items' => [
                [
                    'sale_item_id' => $saleItem->id,
                    'quantity' => 1,
                    'restock' => true,
                    'condition' => 'Damaged'
                ]
            ]
        ];

        $this->returnService->processReturn($returnData, $user);

        // Assertions
        $this->assertDatabaseHas('returns', [
            'sale_id' => $sale->id,
            'refund_amount' => 10.00,
            'reason' => 'Defective',
        ]);

        $this->assertDatabaseHas('return_items', [
            'sale_item_id' => $saleItem->id,
            'quantity' => 1,
            'is_restocked' => 1,
        ]);

        // Verify Audit Log
        $this->assertDatabaseHas('audit_trail', [
            'entity_type' => 'inventory',
            'entity_id' => $inventory->id,
            'action' => 'RETURN_RESTOCK',
            'actor_user_id' => $user->id,
        ]);

        // Verify Inventory Increment
        $this->assertDatabaseHas('inventory', [
            'id' => $inventory->id,
            'quantity_on_hand' => 11, // 10 + 1
        ]);
    }
}
