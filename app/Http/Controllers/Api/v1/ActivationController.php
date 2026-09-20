<?php

namespace App\Http\Controllers\Api\v1;

use AbacPermissions\Models\Account;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ActivationController extends Controller
{
    public function __construct(private readonly LicenseService $licenses) {}

    public function activate(Request $request)
    {
        abort_unless(config('sync.role') === 'parent', 404);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_fingerprint' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'pharmacy_name' => ['nullable', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'ulid'],
        ]);
        abort_if(app()->environment('production') && !$request->secure(), 426, 'Device activation requires HTTPS.');

        $user = \App\Models\User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password) || !$user->is_active) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $account = $user->accounts()->first();
        if (!$account) {
            return response()->json(['message' => 'The user is not assigned to a pharmacy account.'], 403);
        }
        $licenseExpiry = data_get($account->metadata, 'license_expires_at');
        abort_if($licenseExpiry && now()->greaterThan(\Carbon\CarbonImmutable::parse($licenseExpiry)), 403, 'The pharmacy license has expired.');

        $result = DB::transaction(function () use ($validated, $account, $user) {
            $branchQuery = DB::table('branches')->where('account_id', $account->id)->where('is_active', true);
            if (!empty($validated['branch_id'])) {
                $branchQuery->where('branch_id', $validated['branch_id']);
            }
            $branch = $branchQuery->first();
            if (!empty($validated['branch_id']) && !$branch) {
                abort(422, 'The selected branch does not belong to this pharmacy or is inactive.');
            }
            if (!$branch) {
                $branchId = (string) Str::ulid();
                DB::table('branches')->insert([
                    'branch_id' => $branchId,
                    'account_id' => $account->id,
                    'name' => $validated['branch_name'] ?? 'Main Branch',
                    'code' => 'MAIN',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $branch = DB::table('branches')->where('branch_id', $branchId)->first();
            }

            $maxDevices = (int) data_get($account->metadata, 'max_devices', 5);
            $fingerprint = hash('sha256', $validated['device_fingerprint']);
            $device = Device::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $account->id)->where('serial_number', $fingerprint)->first();
            $plainToken = Str::random(64);
            if ($device) {
                abort_if($device->trust_status === 'revoked', 403, 'This device has been revoked.');
                $device->update([
                    'branch_id' => $branch->branch_id,
                    'device_name' => $validated['device_name'] ?? $device->device_name,
                    'sync_token_hash' => hash('sha256', $plainToken),
                    'trust_status' => 'active',
                ]);
            } else {
                $activeDevices = Device::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $account->id)->where('trust_status', 'active')
                    ->where(fn ($query) => $query->whereNull('device_type')->orWhere('device_type', '!=', 'cloud'))
                    ->count();
                abort_if($activeDevices >= $maxDevices, 403, 'The licensed device limit has been reached.');
                $device = Device::create([
                    'device_id' => (string) Str::ulid(), 'account_id' => $account->id,
                    'branch_id' => $branch->branch_id,
                    'device_name' => $validated['device_name'] ?? $validated['device_fingerprint'],
                    'serial_number' => $fingerprint, 'sync_token_hash' => hash('sha256', $plainToken),
                    'trust_status' => 'active',
                ]);
            }

            $license = $this->licenses->signPayload([
                'license_id' => (string) Str::ulid(),
                'account_id' => $account->id,
                'branch_id' => $branch->branch_id,
                'device_id' => $device->device_id,
                'not_before' => now()->subMinute()->toIso8601String(),
                'expires_at' => data_get($account->metadata, 'license_expires_at', now()->addYear()->toIso8601String()),
                'max_devices' => $maxDevices,
                'features' => data_get($account->metadata, 'licensed_features', ['pos', 'inventory', 'sync', 'accounting']),
            ]);

            return compact('account', 'branch', 'device', 'plainToken', 'license', 'user');
        });

        return response()->json([
            'sync_token' => $result['plainToken'],
            'license' => $result['license'],
            'account' => $result['account']->only(['id', 'name', 'slug', 'plan', 'metadata']),
            'branch' => (array) $result['branch'],
            'device' => $result['device']->only(['device_id', 'account_id', 'branch_id', 'device_name']),
            // The child needs the existing hash for offline authentication. This endpoint must only be served over TLS.
            'users' => [array_merge($result['user']->only(['id', 'name', 'email', 'is_active']), [
                'password' => $result['user']->getAuthPassword(),
            ])],
            'access_snapshot' => app(\App\Services\AccessSnapshotService::class)->export((string) $result['account']->id),
        ]);
    }
}
