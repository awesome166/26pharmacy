<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use AbacPermissions\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        static::ensureDefaultsAvailable($accountId);
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

        $value = static::normalizeStoredValue($value);
        $result = static::upsertSetting($key, $accountId, $value, $type);

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
        'accounting_enabled'                        => false,

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

        static::ensureDefaultsAvailable($accountId);

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
     * Ensure persistent rows exist for the current context.
     * This avoids "missing config" states on first use.
     */
    public static function ensureDefaultsAvailable(?string $accountId = null, array $overrides = []): void
    {
        if ($accountId !== null) {
            static::seedTenantDefaults($accountId, $overrides);
            static::seedPlatformDefaults();
            return;
        }

        static::seedPlatformDefaults($overrides);
    }

    public static function seedTenantDefaults(string $accountId, array $overrides = []): void
    {
        foreach (static::$defaults as $key => $default) {
            if (str_starts_with($key, 'system_')) {
                continue;
            }
            static::insertIfMissing($key, $accountId, static::normalizeStoredValue($default), 'tenant');
        }

        foreach ($overrides as $key => $value) {
            if (str_starts_with((string) $key, 'system_')) {
                continue;
            }

            static::upsertSetting($key, $accountId, static::normalizeStoredValue($value), 'tenant');
        }

        static::clearCache($accountId);
    }

    public static function seedPlatformDefaults(array $overrides = []): void
    {
        foreach (static::$defaults as $key => $default) {
            if (!str_starts_with($key, 'system_')) {
                continue;
            }
            static::insertIfMissing($key, null, static::normalizeStoredValue($default), 'platform');
        }

        foreach ($overrides as $key => $value) {
            if (!str_starts_with((string) $key, 'system_')) {
                continue;
            }

            static::upsertSetting($key, null, static::normalizeStoredValue($value), 'platform');
        }

        static::clearCache(null);
    }

    /**
     * Clear cache for system settings.
     *
     * @param string|null $accountId Account ID to clear cache for
     * @param string|null $key Specific key to clear, or null to clear all
     */
    public static function clearCache(?string $accountId = null, ?string $key = null)
    {
        $resolvedAccountId = $accountId ?? app(TenantContext::class)->getAccountId();

        if ($key) {
            // Clear specific setting cache
            Cache::forget("system_setting:{$resolvedAccountId}:{$key}");
        }

        // Always clear merged settings cache when any setting changes
        Cache::forget("system_settings:merged:{$resolvedAccountId}");
        Cache::forget("system_settings:merged:null");
    }

    protected static function normalizeStoredValue($value)
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }

    protected static function upsertSetting(string $key, ?string $accountId, $value, string $type): bool
    {
        $now = now();
        $query = DB::table('system_settings')->where('key', $key);
        $accountId === null
            ? $query->whereNull('account_id')
            : $query->where('account_id', $accountId);

        $existing = $query->first();

        if ($existing) {
            return (bool) $query->update([
                'value' => $value,
                'type' => $type,
                'updated_at' => $now,
            ]);
        }

        return (bool) DB::table('system_settings')->insert([
            'key' => $key,
            'account_id' => $accountId,
            'value' => $value,
            'type' => $type,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected static function insertIfMissing(string $key, ?string $accountId, $value, string $type): bool
    {
        $query = DB::table('system_settings')->where('key', $key);
        $accountId === null
            ? $query->whereNull('account_id')
            : $query->where('account_id', $accountId);

        if ($query->exists()) {
            return true;
        }

        return static::upsertSetting($key, $accountId, $value, $type);
    }
}
