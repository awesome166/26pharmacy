<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SystemSetting;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use AbacPermissions\Tenancy\TenantContext;

class SystemSettingRetrievalTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_retrieve_platform_setting_while_impersonating_tenant()
    {
        // 1. Create a platform setting (account_id = null)
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'test_platform_key', 'account_id' => null],
            ['value' => 'true', 'type' => 'boolean']
        );

        // 2. Create a tenant
        $account = Account::create(['name' => 'Test Tenant', 'slug' => 'test-tenant']);

        // 3. Set context to this tenant
        $context = app(TenantContext::class);
        $context->setAccount($account);

        // 4. Verification 1: SystemSetting::getValue
        // Before the fix, this would fail (return default) because of Global Scope
        $value = SystemSetting::getValue('test_platform_key', 'default');

        $this->assertEquals('true', $value, "Failed to retrieve platform setting via getValue() while in tenant context");

        // 5. Verification 2: Controller Logic Simulation
        // effectively what the controller does:
        $platformSettings = SystemSetting::withoutTenant()->whereNull('account_id')->pluck('value', 'key');

        $this->assertTrue($platformSettings->has('test_platform_key'), "Controller logic failed to retrieve platform setting key");
        $this->assertEquals('true', $platformSettings->get('test_platform_key'), "Controller logic retrieved wrong value");

        // Cleanup
        DB::table('system_settings')->where('key', 'test_platform_key')->delete();
        $account->delete();
    }
}
