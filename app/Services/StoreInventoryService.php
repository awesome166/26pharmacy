<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Drug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StoreInventoryService
{
    /**
     * Get optimized inventory for POS store page.
     * Prioritizes top-selling products and applies aggressive filtering.
     */
    public function getStoreInventory(int $perPage = 50, ?string $search = null, ?string $branchId = null)
    {
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        if (!$accountId) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $branchId ??= app(DeviceContextService::class)->currentBranchId((string) $accountId);
        $cacheVersion = Cache::get("store:version:account:{$accountId}:branch:{$branchId}", 1);

        $cacheKey = "store:inventory:account:{$accountId}:branch:{$branchId}:version:{$cacheVersion}:search:"
            . md5($search ?? 'all') . ":per_page:{$perPage}";

        return Cache::remember($cacheKey, 300, function () use ($perPage, $search, $branchId) {
            $query = Inventory::select([
                'inventory.id',
                'inventory.branch_id',
                'inventory.drug_id',
                'inventory.batch_id',
                'inventory.quantity_on_hand',
                'inventory.selling_price',
                'inventory.cost_price',
                'inventory.location',
                'inventory.is_active'
            ])
            ->with([
                'drug:id,name,strength,generic_name,drug_class,is_prescription,is_controlled,is_narcotic',
                'batch:id,expiry_date,is_active'
            ])
            ->where('inventory.branch_id', $branchId)
            ->where('inventory.is_active', true)
            ->where('inventory.quantity_on_hand', '>', 0)
            ->whereHas('batch', fn ($query) => $query->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('expiry_date')->orWhereDate('expiry_date', '>=', now()->toDateString());
                }));

            // Apply search if provided
            if ($search) {
                $query = $this->applySearch($query, $search);
            } else {
                // When no search, prioritize top-selling products
                $query = $this->prioritizeTopSellers($query);
            }

            return $query->paginate($perPage);
        });
    }

    /**
     * Apply optimized search using joins instead of whereHas.
     */
    protected function applySearch($query, string $search)
    {
        return $query->join('drugs', 'inventory.drug_id', '=', 'drugs.id')
            ->leftJoin('batches', 'inventory.batch_id', '=', 'batches.id')
            ->where(function ($q) use ($search) {
                // Remove leading % for better index usage
                $q->where('drugs.name', 'like', "{$search}%")
                    ->orWhere('drugs.generic_name', 'like', "{$search}%")
                    ->orWhere('batches.id', 'like', "{$search}%");
            })
            ->select('inventory.*'); // Ensure we only select inventory columns
    }

    /**
     * Prioritize top-selling products by joining with sales data.
     * This ensures frequently sold items appear first in the POS.
     */
    protected function prioritizeTopSellers($query)
    {
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        // Get top-selling drug IDs from the last 30 days
        $topSellingDrugIds = $this->getTopSellingDrugIds($accountId);

        if ($topSellingDrugIds->isNotEmpty()) {
            $placeholders = $topSellingDrugIds->map(fn($i) => '?')->implode(',');
            $query->orderByRaw(
                "CASE WHEN inventory.drug_id IN ({$placeholders}) THEN 0 ELSE 1 END",
                $topSellingDrugIds->values()->toArray()
            );
        }

        // Secondary sort by drug name for consistency
        $query->join('drugs', 'inventory.drug_id', '=', 'drugs.id')
            ->orderBy('drugs.name', 'asc')
            ->select('inventory.*');

        return $query;
    }

    /**
     * Get top-selling drug IDs based on recent sales.
     * Cached for 1 hour to avoid expensive aggregation queries.
     * SECURITY: Uses Eloquent with UsesTenant trait for automatic account scoping.
     */
    protected function getTopSellingDrugIds(string $accountId)
    {
        $cacheKey = "store:top_sellers:account:{$accountId}";

        return Cache::remember($cacheKey, 3600, function () {
            // Use Eloquent Sale model which has UsesTenant trait
            // This automatically scopes to current account
            return \App\Models\Sale::query()
                ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.finalized_at', '>=', now()->subDays(30))
                ->select('sale_items.drug_id', DB::raw('SUM(sale_items.quantity) as total_sold'))
                ->groupBy('sale_items.drug_id')
                ->orderByDesc('total_sold')
                ->limit(50) // Top 50 products
                ->pluck('drug_id');
        });
    }

    /**
     * Get quick stats for the store dashboard.
     */
    public function getStoreStats()
    {
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        if (!$accountId) {
            return [
                'total_products' => 0,
                'low_stock_count' => 0,
                'out_of_stock_count' => 0,
            ];
        }

        $cacheKey = "store:stats:account:{$accountId}";

        return Cache::remember($cacheKey, 600, function () {
            return [
                'total_products' => Inventory::where('is_active', true)->count(),
                'low_stock_count' => Inventory::where('is_active', true)
                    ->whereColumn('quantity_on_hand', '<=', 'reorder_level')
                    ->count(),
                'out_of_stock_count' => Inventory::where('is_active', true)
                    ->where('quantity_on_hand', 0)
                    ->count(),
            ];
        });
    }

    /**
     * Invalidate cache for this account.
     * Call this after sales or inventory updates.
     */
    public function invalidateCache(?string $accountId = null, ?string $branchId = null)
    {
        $accountId = $accountId ?? app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        if (!$accountId) {
            return;
        }

        // Clear all store-related caches for this account
        Cache::forget("store:stats:account:{$accountId}");
        Cache::forget("store:top_sellers:account:{$accountId}");
        if ($branchId) {
            Cache::increment("store:version:account:{$accountId}:branch:{$branchId}");
        } else {
            \App\Models\Device::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $accountId)->whereNotNull('branch_id')->distinct()
                ->pluck('branch_id')->each(fn ($id) => Cache::increment("store:version:account:{$accountId}:branch:{$id}"));
        }

        // Clear paginated inventory caches (this is a pattern match, may need cache tagging)
        // For now, we'll rely on TTL expiration
    }
}
