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
     * The canonical list of all config keys with their default values.
     * These serve as the base layer so missing DB entries always return false.
     */
    public static array $defaults = [
        // Inventory & Stock (tenant settings)
        'inventory_batch_mode'                      => false,
        'inventory_prevent_negative_stock'          => false,
        'inventory_require_approval_for_adjustments'=> false,

        // Sales & POS (tenant settings)
        'sales_require_prescription'                => false,
        'sales_add_tax'                             => false,
        'sales_enable_loyalty'                      => false,
        'sales_print_auto_receipt'                  => false,

        // System / Platform settings
        'system_maintenance_mode'                   => false,
        'system_debug_mode'                         => false,
    ];

    /**
     * Get merged settings for the current tenant context.
     * Layers: model defaults (false) → platform DB → tenant DB.
     *
     * @param string|null $accountId Optional account ID, defaults to current tenant context
     * @return array ['settings' => Collection, 'exists' => bool]
     */
    public static function getMergedSettings(?string $accountId = null)
    {
        if ($accountId === null) {
            $accountId = app(TenantContext::class)->getAccountId();
        }

        // 1. Start with all-false model defaults
        $defaults = collect(static::$defaults);

        // 2. Overlay platform DB settings (keys starting with 'system_')
        $platformSettings = self::withoutTenant()
            ->whereNull('account_id')
            ->pluck('value', 'key');

        // 3. Overlay tenant DB settings
        $tenantSettings = $accountId
            ? self::where('account_id', $accountId)->pluck('value', 'key')
            : collect();

        // 4. Merge in order: defaults → platform → tenant
        $finalSettings = $defaults
            ->merge($platformSettings)
            ->merge($tenantSettings)
            ->map(function ($value) {
                // Cast values to booleans
                if ($value === true  || $value == 1 || $value === 'true')  return true;
                if ($value === false || $value == 0 || $value === 'false' || $value === null) return false;
                return $value;
            });

        $exists = $accountId ? self::where('account_id', $accountId)->exists() : false;

        return [
            'settings' => $finalSettings,
            'exists'   => $exists,
        ];
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
