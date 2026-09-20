<?php

namespace App\Services;

use AbacPermissions\Tenancy\TenantContext;
use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DomainEventService
{
    public function __construct(
        private readonly EventLedgerService $ledger,
        private readonly DeviceContextService $devices,
    ) {}

    public function record(string $eventType, array $payload, ?string $actorUserId = null, ?string $branchId = null): object
    {
        $accountId = (string) app(TenantContext::class)->getAccountId();
        if (!$accountId) {
            throw new RuntimeException('A pharmacy account is required to record a domain event.');
        }

        $device = config('sync.role') === 'parent'
            ? $this->parentAuthorityDevice($accountId, $branchId)
            : $this->devices->currentDevice($accountId);

        return $this->ledger->emitEvent([
            'account_id' => $accountId,
            'device_id' => $device->device_id,
            'actor_user_id' => $actorUserId,
            'event_type' => $eventType,
            'project_locally' => false,
            'event_payload' => $payload,
        ]);
    }

    private function parentAuthorityDevice(string $accountId, ?string $branchId): Device
    {
        $branchId ??= DB::table('branches')->where('account_id', $accountId)
            ->where('is_active', true)->orderBy('created_at')->value('branch_id');
        if (!$branchId) {
            throw new RuntimeException('Create an active branch before changing synchronized pharmacy data.');
        }

        // A device owns one hash chain and one branch. Using an authority device
        // per branch prevents cloud-originated events from being stamped with a
        // different branch merely because that branch was created first.
        $serial = hash('sha256', 'parent-authority:'.$accountId.':'.$branchId);
        return Device::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)->firstOrCreate([
            'account_id' => $accountId,
            'serial_number' => $serial,
        ], [
            'device_id' => (string) Str::ulid(),
            'branch_id' => $branchId,
            'device_name' => 'Cloud Authority',
            'device_type' => 'cloud',
            'trust_status' => 'active',
        ]);
    }
}
