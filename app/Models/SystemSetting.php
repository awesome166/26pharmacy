<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use AbacPermissions\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class SystemSetting extends Model
{
   use \AbacPermissions\Tenancy\UsesTenant;


    public $incrementing = false;
    protected $primaryKey = ['key', 'account_id'];
    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'account_id',
        'value',
        'type',
    ];

    protected $casts = [
        'value' => 'boolean',
    ];

    /**
     * Get the value for a specific key, falling back to platform setting if needed.
     */
    public static function getValue(string $key, ?string $default = null)
    {
        $accountId = app(TenantContext::class)->getAccountId();
        $cacheKey = "system_setting:{$accountId}:{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $accountId, $default) {
            // Try to find tenant-specific setting first
            if ($accountId) {
                $setting = self::where('key', $key)->where('account_id', $accountId)->first();
                if ($setting) {
                    return $setting->value;
                }
            }

            // Fallback to platform setting (account_id = null)
            $platformSetting = self::withoutTenant()->where('key', $key)->whereNull('account_id')->first();

            return $platformSetting ? $platformSetting->value : $default;
        });
    }

    /**
     * Set a value for the current tenant (or platform if no tenant).
     */
    public static function setValue(string $key, $value)
    {
        $accountId = app(TenantContext::class)->getAccountId();
        $type = $accountId ? 'tenant' : 'platform';

        // Validate namespace protection:
        // - Platform (account_id = null) can only set keys starting with "system"
        // - Tenants (account_id != null) can only set keys NOT starting with "system"
        $keyStartsWithSystem = str_starts_with($key, 'system');

        if ($accountId === null && !$keyStartsWithSystem) {
            throw new \InvalidArgumentException(
                "Platform can only set system configuration keys. " .
                "Keys not starting with 'system' are reserved for tenant configurations."
            );
        }

        if ($accountId !== null && $keyStartsWithSystem) {
            throw new \InvalidArgumentException(
                "Tenants cannot set platform configuration keys. " .
                "Keys starting with 'system' are reserved for platform configurations."
            );
        }

        // Normalize boolean values to 1/0 for consistent storage
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        // Use DB updateOrInsert to avoid Eloquent composite key limitations
        $result = \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
            ['key' => $key, 'account_id' => $accountId],
            [
                'value' => $value,
                'type' => $type,
                'updated_at' => now(),
                'created_at' => now()
            ]
        );

        // Clear cache for this specific setting and merged settings
        self::clearCache($accountId, $key);

        return $result;
    }

    /**
     * Get merged settings for the current tenant context.
     * Platform settings serve as defaults, tenant settings override them.
     *
     * @param string|null $accountId Optional account ID, defaults to current tenant context
     * @return array ['settings' => Collection, 'exists' => bool]
     */
    public static function getMergedSettings(?string $accountId = null)
    {
        if ($accountId === null) {
            $accountId = app(TenantContext::class)->getAccountId();
        }

        $cacheKey = "system_settings:merged:{$accountId}";

        // return Cache::remember($cacheKey, 3600, function () use ($accountId) {
            // 1. Get all platform settings (defaults from DB)
            $platformSettings = self::withoutTenant()
                ->whereNull('account_id')
                ->pluck('value', 'key');

            // 2. Get all tenant settings
            $tenantSettings = $accountId
                ? self::where('account_id', $accountId)->pluck('value', 'key')
                : collect();

            // 3. Merge: Tenant overrides Platform, using DB data only
            $finalSettings = $platformSettings->merge($tenantSettings)->map(function ($value) {
                // Cast values to booleans where appropriate
                if ($value == 1 || $value === 'true' || $value === true) return true;
                if ($value == 0 || $value === 'false' || $value === false || $value === null) return false;
                return $value;
            });

            $exists = self::where('account_id', $accountId)->exists();

            return [
                'settings' => $finalSettings,
                'exists' => $exists
            ];
        // });
    }

    /**
     * Clear cache for system settings.
     *
     * @param string|null $accountId Account ID to clear cache for
     * @param string|null $key Specific key to clear, or null to clear all
     */
    public static function clearCache(?string $accountId = null, ?string $key = null)
    {
        if ($accountId === null) {
            $accountId = app(TenantContext::class)->getAccountId();
        }

        if ($key) {
            // Clear specific setting cache
            Cache::forget("system_setting:{$accountId}:{$key}");
        }

        // Always clear merged settings cache when any setting changes
        Cache::forget("system_settings:merged:{$accountId}");

        // Also clear platform merged settings if this is a platform setting
        if ($accountId === null) {
            Cache::forget("system_settings:merged:null");
        }
    }
}
