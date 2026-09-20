<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Branch;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SystemConfigTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_persists_system_configuration_settings()
    {
        // Mock Tenant Context
        $this->withoutMiddleware();

        $account = \AbacPermissions\Models\Account::create(['name' => 'Test', 'slug' => 'test']);
        Branch::create([
            'branch_id' => (string) \Illuminate\Support\Str::ulid(),
            'account_id' => $account->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) use ($account) {
            $mock->shouldReceive('getAccountId')->andReturn($account->id);
        });

        // Define expected defaults (all false)
        $defaults = [
            'inventory_batch_mode' => false,
            'sales_enable_loyalty' => false,
            'system_debug_mode' => false,
        ];

        // 1. Verify defaults via GET and 'exists' flag
        $response = $this->getJson('/app/config');
        $response->assertStatus(200)
            ->assertJsonPath('exists', true);

        // 2. First Save (POST)
        $newSettings = [
            'inventory_batch_mode' => true,
            'sales_enable_loyalty' => true,
        ];

        $updateResponse = $this->postJson('/app/config', [
            'settings' => $newSettings
        ]);
        $updateResponse->assertStatus(200);

        // 3. Verify persistence (Check DB string value '1')
        $this->assertDatabaseHas('system_settings', [
            'key' => 'inventory_batch_mode',
            'value' => '1',
            'account_id' => $account->id,
            'type' => 'tenant'
        ]);

        // 4. Verify via GET again - should now exist
        $finalResponse = $this->getJson('/app/config');
        $finalResponse->assertStatus(200)
            ->assertJsonPath('exists', true);

         // 5. Second Save (PATCH)
        $patchSettings = [
            'inventory_batch_mode' => false, // Toggle back
        ];

        $patchResponse = $this->patchJson('/app/config', [
            'settings' => $patchSettings
        ]);
        $patchResponse->assertStatus(200);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'inventory_batch_mode',
            'value' => '0',
            'account_id' => $account->id
        ]);
    }

    #[Test]
    public function platform_cannot_set_tenant_specific_keys()
    {
        // Mock Platform Context (no account)
        $this->withoutMiddleware();

        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) {
            $mock->shouldReceive('getAccountId')->andReturn(null);
        });

        // Tenant keys cannot be written without a tenant context.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Platform can only set system configuration keys");

        SystemSetting::setValue('inventory_batch_mode', true);
    }

    #[Test]
    public function tenant_cannot_set_platform_keys()
    {
        // Mock Tenant Context
        $this->withoutMiddleware();

        $account = \AbacPermissions\Models\Account::create(['name' => 'Test', 'slug' => 'test']);

        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) use ($account) {
            $mock->shouldReceive('getAccountId')->andReturn($account->id);
        });

        // Platform keys cannot be overridden by a tenant.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Tenants cannot set platform configuration keys");

        SystemSetting::setValue('system_debug_mode', true);
    }

    #[Test]
    public function platform_can_set_system_keys()
    {
        // Mock Platform Context (no account)
        $this->withoutMiddleware();

        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) {
            $mock->shouldReceive('getAccountId')->andReturn(null);
        });

        SystemSetting::setValue('system_debug_mode', true);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'system_debug_mode',
            'value' => '1',
            'account_id' => null,
            'type' => 'platform'
        ]);
    }

    #[Test]
    public function tenant_can_set_tenant_keys()
    {
        // Mock Tenant Context
        $this->withoutMiddleware();

        $account = \AbacPermissions\Models\Account::create(['name' => 'Test', 'slug' => 'test']);

        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) use ($account) {
            $mock->shouldReceive('getAccountId')->andReturn($account->id);
        });

        SystemSetting::setValue('inventory_batch_mode', true);

        $this->assertDatabaseHas('system_settings', [
            'key' => 'inventory_batch_mode',
            'value' => '1',
            'account_id' => $account->id,
            'type' => 'tenant'
        ]);
    }
}
