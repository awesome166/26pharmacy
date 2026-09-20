<?php

namespace Tests\Feature\Inventory;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Branch;
use App\Models\Device;
use AbacPermissions\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BatchModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup Account and User context
        $this->user = User::factory()->create();
        $this->account = Account::create([
            'name' => 'Test Pharmacy',
            'slug' => 'test-pharmacy'
        ]);
        $this->user->accounts()->attach($this->account);

        $branchId = (string) Str::ulid();
        Branch::create([
            'branch_id' => $branchId,
            'account_id' => $this->account->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);
        $deviceId = (string) Str::ulid();
        Device::create([
            'device_id' => $deviceId,
            'account_id' => $this->account->id,
            'branch_id' => $branchId,
            'device_name' => 'Till 1',
            'trust_status' => 'active',
        ]);
        config([
            'sync.role' => 'child',
            'sync.client_id' => $deviceId,
            'sync.queue_connection' => 'database',
        ]);

        // Mock Tenant Context
        $this->withoutMiddleware();

        // We need to ensure middleware or context sets the account
        // Since we are mocking, we can just ensure our controller logic picks it up
        // Usually done via middleware or header
    }

    #[Test]
    public function it_adds_to_inventory_automatically_in_direct_mode()
    {
        // 1. Set mode to OFF (Direct Mode)
        // We need to mock SystemSetting::getValue or create the setting
        // Since we implemented SystemSetting as a model, we can just create it
        SystemSetting::create([
            'key' => 'inventory_batch_mode',
            'value' => 0, // False
            'account_id' => $this->account->id
        ]);

        // Mock Tenant/App Context if needed by Controller
        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) {
            $mock->shouldReceive('getAccountId')->andReturn($this->account->id);
        });

        $drug = \App\Models\Drug::create([
            'drug_id' => Str::ulid(),
            'name' => 'Test Drug',
            'strength' => '10mg',
             'is_active' => true,
        ]);

        // 2. Create Batch
        $response = $this->postJson('/api/v1/batches', [
            'drug_id' => $drug->id,
            'expiry_date' => now()->addYear()->toDateString(),
            'lot_number' => 'LOT-123',
            'supplier' => 'Pharma Supply Ltd',
            'manufacturer' => 'Pfizer',
            'cost_price' => 10.50,
            'quantity' => 100,
            'selling_price' => 20.00, // Extra field for inventory
        ]);

        $response->assertStatus(201);

        // 3. Verify Inventory Created
        $this->assertDatabaseHas('batches', ['lot_number' => 'LOT-123']);
        $this->assertDatabaseHas('inventory', [
            'quantity_on_hand' => 100,
            'selling_price' => 20.00
        ]);
    }

    #[Test]
    public function it_does_not_add_to_inventory_in_batch_mode()
    {
        // 1. Set mode to ON (Batch Mode active)
        SystemSetting::create([
            'key' => 'inventory_batch_mode',
            'value' => 1, // True
            'account_id' => $this->account->id
        ]);

        // Mock Tenant/App Context
        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) {
            $mock->shouldReceive('getAccountId')->andReturn($this->account->id);
        });

        $drug = \App\Models\Drug::create([
            'drug_id' => Str::ulid(),
            'name' => 'Test Drug 2',
            'strength' => '50mg',
             'is_active' => true,
        ]);

        // 2. Create Batch
        $response = $this->postJson('/api/v1/batches', [
            'drug_id' => $drug->id,
            'expiry_date' => now()->addYear()->toDateString(),
            'lot_number' => 'LOT-456',
            'supplier' => 'Pharma Supply Ltd',
            'manufacturer' => 'Moderna',
            'cost_price' => 15.00,
            'quantity' => 50,
            // selling_price might be sent but should be ignored or irrelevant if logic holds
        ]);

        $response->assertStatus(201);

        // 3. Verify Inventory NOT Created
        $this->assertDatabaseHas('batches', ['lot_number' => 'LOT-456']);
        $this->assertDatabaseMissing('inventory', [
            'quantity_on_hand' => 50,
            // We check for matching stock from this batch is missing
        ]);
    }
}
