<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use AbacPermissions\Tenancy\TenantContext;

class DashboardController extends Controller
{
    /**
     * Cache TTL in seconds (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Get dashboard statistics
     */
    public function stats(Request $request)
    {
        $accountId = app(TenantContext::class)->getAccountId();
        $cacheKey = 'dashboard.stats.' . $accountId;

        $stats = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            $today = Carbon::today();
            $startOfMonth = Carbon::now()->startOfMonth();

            // Today's sales count and revenue (Explicit account_id filter)
            $todaySales = Sale::where('account_id', $accountId)
                ->whereDate('finalized_at', $today)
                ->get();
            $todaySalesCount = $todaySales->count();
            $todayRevenue = $todaySales->sum('total_amount');

            // This month's sales count (Explicit account_id filter)
            $monthSalesCount = Sale::where('account_id', $accountId)
                ->whereBetween('finalized_at', [$startOfMonth, Carbon::now()])
                ->count();

            // Low stock items (Explicit account_id filter)
            $lowStockCount = Inventory::where('account_id', $accountId)
                ->where('quantity_on_hand', '<', 10)
                ->count();

            return [
                'today_sales_count' => $todaySalesCount,
                'today_revenue' => (float) $todayRevenue,
                'low_stock_count' => $lowStockCount,
                'month_sales_count' => $monthSalesCount,
            ];
        });

        return response()->json($stats);
    }

    /**
     * Get sales trend data
     */
    public function salesTrend(Request $request)
    {
        $days = (int) $request->input('days', 7);
        $accountId = app(TenantContext::class)->getAccountId();
        $cacheKey = 'dashboard.sales_trend.' . $accountId . '.' . $days;

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($days, $accountId) {
            $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

            // Use Eloquent with explicit account_id filter
            $salesData = Sale::where('account_id', $accountId)
                ->whereBetween('finalized_at', [$startDate, Carbon::now()])
                ->selectRaw('DATE(finalized_at) as date, COUNT(*) as count, SUM(total_amount) as revenue')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            // Create array with all dates in range
            $labels = [];
            $sales = [];
            $revenue = [];

            for ($i = 0; $i < $days; $i++) {
                $date = Carbon::now()->subDays($days - 1 - $i);
                $dateStr = $date->format('Y-m-d');
                $labels[] = $date->format('M d');

                $dayData = $salesData->get($dateStr);
                $sales[] = $dayData ? $dayData->count : 0;
                $revenue[] = $dayData ? (float) $dayData->revenue : 0;
            }

            return [
                'labels' => $labels,
                'sales' => $sales,
                'revenue' => $revenue,
            ];
        });

        return response()->json($data);
    }

    /**
     * Get top selling products
     */
    public function topProducts(Request $request)
    {
        $limit = (int) $request->input('limit', 10);
        $accountId = app(TenantContext::class)->getAccountId();
        $cacheKey = 'dashboard.top_products.' . $accountId . '.' . $limit;

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($limit, $accountId) {
            // Use Eloquent with relationships and explicit account_id filter via sale
            $topProducts = SaleItem::whereHas('sale', function ($query) use ($accountId) {
                    $query->where('account_id', $accountId);
                })
                ->with('drug:id,name')
                ->selectRaw('drug_id, SUM(quantity) as total_quantity')
                ->groupBy('drug_id')
                ->orderByDesc('total_quantity')
                ->limit($limit)
                ->get();

            return [
                'labels' => $topProducts->pluck('drug.name')->toArray(),
                'data' => $topProducts->pluck('total_quantity')->map(fn($val) => (int) $val)->toArray(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Get payment methods breakdown
     */
    public function paymentBreakdown(Request $request)
    {
        $accountId = app(TenantContext::class)->getAccountId();
        $cacheKey = 'dashboard.payment_breakdown.' . $accountId;

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            // Use Eloquent with explicit account_id filter
            $paymentData = Sale::where('account_id', $accountId)
                ->select('payment_type')
                ->selectRaw('COUNT(*) as count')
                ->whereNotNull('payment_type')
                ->groupBy('payment_type')
                ->get();

            return [
                'labels' => $paymentData->pluck('payment_type')->map(fn($type) => ucfirst($type))->toArray(),
                'data' => $paymentData->pluck('count')->map(fn($val) => (int) $val)->toArray(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Clear dashboard cache (useful after sales are created)
     */
    public function clearCache()
    {
        $accountId = app(TenantContext::class)->getAccountId();

        Cache::forget('dashboard.stats.' . $accountId);
        Cache::forget('dashboard.sales_trend.' . $accountId . '.7');
        Cache::forget('dashboard.sales_trend.' . $accountId . '.30');
        Cache::forget('dashboard.top_products.' . $accountId . '.10');
        Cache::forget('dashboard.payment_breakdown.' . $accountId);

        return response()->json(['message' => 'Dashboard cache cleared']);
    }
}
