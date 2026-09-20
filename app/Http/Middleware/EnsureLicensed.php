<?php

namespace App\Http\Middleware;

use AbacPermissions\Models\Account;
use AbacPermissions\Tenancy\TenantContext;
use App\Services\DeviceContextService;
use App\Services\LicenseService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicensed
{
    public function __construct(
        private readonly LicenseService $licenses,
        private readonly DeviceContextService $devices,
    ) {}

    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        if (app()->environment('testing') && !config('sync.enforce_license_in_tests')) {
            return $next($request);
        }

        if (config('sync.role') === 'parent') {
            $accountId = app(TenantContext::class)->getAccountId();
            $account = $accountId ? Account::find($accountId) : null;
            $expiresAt = $account ? data_get($account->metadata, 'license_expires_at') : null;
            $features = $account ? data_get($account->metadata, 'licensed_features') : null;

            if ($account && (bool) data_get($account->metadata, 'license_suspended', false)) {
                return response()->json(['message' => 'The pharmacy license is suspended.'], 403);
            }
            if ($expiresAt && CarbonImmutable::parse($expiresAt)->isPast()) {
                return response()->json(['message' => 'The pharmacy license has expired.'], 403);
            }
            if ($feature && is_array($features) && !in_array($feature, $features, true)) {
                return response()->json(['message' => "Feature {$feature} is not licensed."], 403);
            }

            return $next($request);
        }

        $accountId = app(TenantContext::class)->getAccountId();
        $row = DB::table('system_settings')->where('key', 'license')->where('account_id', $accountId)->first();

        try {
            $device = $this->devices->currentDevice((string) $accountId);
            $this->licenses->verifyLicense(json_decode($row?->value ?? '', true, flags: JSON_THROW_ON_ERROR), array_filter([
                'account_id' => $accountId,
                'branch_id' => $device->branch_id,
                'device_id' => $device->device_id,
                'feature' => $feature,
                'allow_grace' => true,
            ]));
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'License check failed: '.$exception->getMessage()], 403);
        }

        return $next($request);
    }
}
