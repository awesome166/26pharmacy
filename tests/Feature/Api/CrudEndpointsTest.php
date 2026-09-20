<?php

namespace Tests\Feature\Api;

use App\Models\Batch;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Drug;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrudEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected string $accountId;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $account = \AbacPermissions\Models\Account::create([
            'name' => 'CRUD Test Account',
            'slug' => 'crud-test-account',
        ]);

        $this->accountId = (string) $account->id;
        config(['sync.role' => 'child', 'sync.queue_connection' => 'database']);

        $branchId = (string) Str::ulid();
        \App\Models\Branch::create([
            'branch_id' => $branchId,
            'account_id' => $this->accountId,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);
        Device::create([
            'device_id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'branch_id' => $branchId,
            'device_name' => 'CRUD Device',
            'trust_status' => 'active',
        ]);

        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) {
            $mock->shouldReceive('getAccountId')->andReturn($this->accountId);
            $mock->shouldReceive('setAccount')->zeroOrMoreTimes();
        });

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authorize::class);
    }

    // -----------------------------------------------------------------------
    //  Drugs
    // -----------------------------------------------------------------------

    #[Test]
    public function drug_index_returns_paginated_results(): void
    {
        Drug::factory()->count(3)->create(['account_id' => $this->accountId]);

        $response = $this->getJson('/api/v1/drugs');

        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    #[Test]
    public function drug_show_returns_single_drug(): void
    {
        $drug = Drug::factory()->create(['account_id' => $this->accountId]);

        $response = $this->getJson("/api/v1/drugs/{$drug->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $drug->id);
        $response->assertJsonPath('data.name', $drug->name);
    }

    #[Test]
    public function drug_store_creates_new_drug(): void
    {
        $payload = [
            'name' => 'Paracetamol',
            'strength' => '500mg',
            'form' => 'tablet',
            'is_prescription' => false,
        ];

        $response = $this->postJson('/api/v1/drugs', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Paracetamol');

        $this->assertDatabaseHas('drugs', [
            'name' => 'Paracetamol',
            'account_id' => $this->accountId,
        ]);
    }

    #[Test]
    public function drug_update_modifies_existing_drug(): void
    {
        $drug = Drug::factory()->create(['account_id' => $this->accountId, 'name' => 'Old Name']);

        $response = $this->putJson("/api/v1/drugs/{$drug->id}", [
            'name' => 'Updated Name',
            'strength' => '250mg',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('drugs', [
            'id' => $drug->id,
            'name' => 'Updated Name',
        ]);
    }

    #[Test]
    public function drug_destroy_removes_drug(): void
    {
        $drug = Drug::factory()->create(['account_id' => $this->accountId]);

        $response = $this->deleteJson("/api/v1/drugs/{$drug->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('drugs', ['id' => $drug->id]);
    }

    #[Test]
    public function drug_show_returns_404_for_missing(): void
    {
        $response = $this->getJson('/api/v1/drugs/' . Str::ulid());
        $response->assertNotFound();
    }

    // -----------------------------------------------------------------------
    //  Inventory
    // -----------------------------------------------------------------------

    #[Test]
    public function inventory_show_returns_single_record(): void
    {
        $drug = Drug::factory()->create(['account_id' => $this->accountId]);
        $batch = Batch::create([
            'id' => Str::ulid(),
            'drug_id' => $drug->id,
            'lot_number' => 'LOT-SHOW',
            'quantity' => 100,
        ]);
        $inventory = Inventory::factory()->create([
            'account_id' => $this->accountId,
            'drug_id' => $drug->id,
            'batch_id' => $batch->id,
        ]);

        $response = $this->getJson("/api/v1/inventory/{$inventory->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $inventory->id);
    }

    #[Test]
    public function inventory_update_modifies_fields(): void
    {
        $drug = Drug::factory()->create(['account_id' => $this->accountId]);
        $batch = Batch::create([
            'id' => Str::ulid(),
            'drug_id' => $drug->id,
            'lot_number' => 'LOT-UPDATE',
            'quantity' => 100,
        ]);
        $inventory = Inventory::factory()->create([
            'account_id' => $this->accountId,
            'drug_id' => $drug->id,
            'batch_id' => $batch->id,
            'selling_price' => 10.00,
            'location' => 'Shelf A',
        ]);

        $response = $this->patchJson("/api/v1/inventory/{$inventory->id}", [
            'selling_price' => 15.50,
            'location' => 'Shelf B',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.selling_price', '15.50');
        $response->assertJsonPath('data.location', 'Shelf B');
    }

    #[Test]
    public function inventory_destroy_deactivates_record(): void
    {
        $drug = Drug::factory()->create(['account_id' => $this->accountId]);
        $batch = Batch::create([
            'id' => Str::ulid(),
            'drug_id' => $drug->id,
            'lot_number' => 'LOT-DEL',
            'quantity' => 100,
        ]);
        $inventory = Inventory::factory()->create([
            'account_id' => $this->accountId,
            'drug_id' => $drug->id,
            'batch_id' => $batch->id,
            'is_active' => true,
            'quantity_on_hand' => 50,
        ]);

        $response = $this->deleteJson("/api/v1/inventory/{$inventory->id}");

        $response->assertOk();
        $this->assertDatabaseHas('inventory', [
            'id' => $inventory->id,
            'is_active' => false,
            'quantity_on_hand' => 0,
        ]);
    }

    // -----------------------------------------------------------------------
    //  Customers
    // -----------------------------------------------------------------------

    #[Test]
    public function customer_index_returns_paginated_results(): void
    {
        Customer::factory()->count(3)->create(['account_id' => $this->accountId]);

        $response = $this->getJson('/api/v1/customers');

        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    #[Test]
    public function customer_show_returns_single_customer(): void
    {
        $customer = Customer::factory()->create(['account_id' => $this->accountId]);

        $response = $this->getJson("/api/v1/customers/{$customer->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $customer->id);
        $response->assertJsonPath('data.name', $customer->name);
    }

    #[Test]
    public function customer_store_creates_new_customer(): void
    {
        $payload = [
            'name' => 'John Doe',
            'phone' => '555-0100',
            'email' => 'john@example.com',
        ];

        $response = $this->postJson('/api/v1/customers', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'John Doe');

        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'account_id' => $this->accountId,
        ]);
    }

    #[Test]
    public function customer_store_rejects_duplicate_phone(): void
    {
        Customer::factory()->create([
            'account_id' => $this->accountId,
            'phone' => '555-0100',
        ]);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Jane Doe',
            'phone' => '555-0100',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    #[Test]
    public function customer_update_modifies_customer(): void
    {
        $customer = Customer::factory()->create([
            'account_id' => $this->accountId,
            'name' => 'Old Name',
        ]);

        $response = $this->putJson("/api/v1/customers/{$customer->id}", [
            'name' => 'New Name',
            'phone' => $customer->phone,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
    }

    #[Test]
    public function customer_destroy_blocked_when_has_sales(): void
    {
        $customer = Customer::factory()->create(['account_id' => $this->accountId]);

        $sale = \App\Models\Sale::create([
            'id' => Str::ulid(),
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'total_amount' => 100,
            'payment_type' => 'cash',
        ]);
        $customer->sales()->attach($sale->id, ['id' => Str::ulid()]);

        $response = $this->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    #[Test]
    public function customer_destroy_allowed_when_no_sales(): void
    {
        $customer = Customer::factory()->create(['account_id' => $this->accountId]);

        $response = $this->deleteJson("/api/v1/customers/{$customer->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }

    // -----------------------------------------------------------------------
    //  Devices
    // -----------------------------------------------------------------------

    #[Test]
    public function device_index_returns_paginated_results(): void
    {
        Device::factory()->count(3)->create(['account_id' => $this->accountId]);

        $response = $this->getJson('/api/v1/devices');

        $response->assertOk();
        $response->assertJsonCount(4, 'data.data');
    }

    #[Test]
    public function device_show_returns_single_device(): void
    {
        $device = Device::factory()->create(['account_id' => $this->accountId]);

        $response = $this->getJson("/api/v1/devices/{$device->device_id}");

        $response->assertOk();
        $response->assertJsonPath('data.device_id', $device->device_id);
        $response->assertJsonPath('data.device_name', $device->device_name);
    }

    #[Test]
    public function device_update_modifies_device(): void
    {
        $device = Device::factory()->create([
            'account_id' => $this->accountId,
            'device_name' => 'Old Terminal',
            'trust_status' => 'active',
        ]);

        $response = $this->patchJson("/api/v1/devices/{$device->device_id}", [
            'device_name' => 'New Terminal',
            'trust_status' => 'revoked',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.device_name', 'New Terminal');
        $response->assertJsonPath('data.trust_status', 'revoked');
    }

    #[Test]
    public function device_revoke_marks_as_revoked(): void
    {
        $device = Device::factory()->create([
            'account_id' => $this->accountId,
            'trust_status' => 'active',
        ]);

        $response = $this->deleteJson("/api/v1/devices/{$device->device_id}");

        $response->assertOk();
        $this->assertDatabaseHas('devices', [
            'device_id' => $device->device_id,
            'trust_status' => 'revoked',
        ]);
    }
}
