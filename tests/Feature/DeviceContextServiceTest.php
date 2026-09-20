<?php

namespace Tests\Feature;

use AbacPermissions\Models\Account;
use App\Models\Branch;
use App\Models\Device;
use App\Services\DeviceContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DeviceContextServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_falls_back_to_an_active_device_in_the_requested_account_when_global_config_is_stale(): void
    {
        [$firstAccount, $firstBranch, $firstDevice] = $this->createAccountDevice('First');
        [$secondAccount, $secondBranch, $secondDevice] = $this->createAccountDevice('Second');

        config(['sync.client_id' => $firstDevice->device_id]);

        $resolved = app(DeviceContextService::class)->currentDevice((string) $secondAccount->id);

        $this->assertSame($secondDevice->device_id, $resolved->device_id);
        $this->assertSame($secondBranch->branch_id, $resolved->branch_id);
    }

    #[Test]
    public function an_account_setting_takes_precedence_over_the_global_device_config(): void
    {
        [$account, $branch, $firstDevice] = $this->createAccountDevice('Configured');
        $preferred = Device::create([
            'device_id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'branch_id' => $branch->branch_id,
            'device_name' => 'Preferred Till',
            'trust_status' => 'active',
        ]);

        config(['sync.client_id' => $firstDevice->device_id]);
        DB::table('system_settings')->insert([
            'key' => 'sync_client_id',
            'account_id' => $account->id,
            'value' => $preferred->device_id,
            'type' => 'tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolved = app(DeviceContextService::class)->currentDevice((string) $account->id);

        $this->assertSame($preferred->device_id, $resolved->device_id);
    }

    #[Test]
    public function it_rejects_an_explicit_device_from_another_account(): void
    {
        [$firstAccount, $firstBranch, $firstDevice] = $this->createAccountDevice('First');
        [$secondAccount] = $this->createAccountDevice('Second');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The requested device is not active for this account and branch.');

        app(DeviceContextService::class)->currentDevice((string) $secondAccount->id, $firstDevice->device_id);
    }

    #[Test]
    public function it_does_not_resolve_a_device_attached_to_an_inactive_branch(): void
    {
        [$account, $branch] = $this->createAccountDevice('Closed');
        $branch->update(['is_active' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active branch device is configured for this installation.');

        app(DeviceContextService::class)->currentDevice((string) $account->id);
    }

    /**
     * @return array{Account, Branch, Device}
     */
    private function createAccountDevice(string $name): array
    {
        $account = Account::create([
            'name' => "{$name} Pharmacy",
            'slug' => Str::slug("{$name} Pharmacy").'-'.Str::lower(Str::random(6)),
        ]);
        $branch = Branch::withoutEvents(fn () => Branch::create([
            'branch_id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => "{$name} Branch",
            'code' => Str::upper(substr($name, 0, 5)),
            'is_active' => true,
        ]));
        $device = Device::withoutEvents(fn () => Device::create([
            'device_id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'branch_id' => $branch->branch_id,
            'device_name' => "{$name} Till",
            'trust_status' => 'active',
        ]));

        return [$account, $branch, $device];
    }
}
