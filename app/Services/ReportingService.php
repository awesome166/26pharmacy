<?php

namespace App\Services;

use AbacPermissions\Tenancy\TenantContext;
use App\Models\FinancialDaySummary;
use App\Models\Sale;
use App\Models\SalesReturn;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ReportingService
{
    public function getDailySummary(string $date, ?string $branchId = null, bool $persist = false): FinancialDaySummary
    {
        $accountId = app(TenantContext::class)->getAccountId();
        if (!$accountId) {
            throw new RuntimeException('A pharmacy account must be selected before generating a report.');
        }

        $branchId ??= app(DeviceContextService::class)->currentBranchId((string) $accountId);
        $branch = DB::table('branches')->where('account_id', $accountId)
            ->where('branch_id', $branchId)->first();
        if (!$branch) {
            throw new RuntimeException('The selected reporting branch does not belong to this pharmacy.');
        }

        $timezone = $branch->timezone ?? config('app.timezone', 'UTC');
        $localDay = CarbonImmutable::parse($date, $timezone)->startOfDay();
        $startUtc = $localDay->utc();
        $endUtc = $localDay->endOfDay()->utc();

        $sales = Sale::query()
            ->with(['items.drug', 'user', 'customers'])
            ->where('branch_id', $branchId)
            ->whereBetween('finalized_at', [$startUtc, $endUtc])
            ->get();
        $returns = SalesReturn::query()
            ->with(['items.saleItem', 'user'])
            ->where('branch_id', $branchId)
            ->whereBetween('returned_at', [$startUtc, $endUtc])
            ->get();

        $grossSales = round((float) $sales->sum('total_amount'), 2);
        $salesNetBeforeReturns = round((float) $sales->sum('subtotal_amount'), 2);
        $salesTax = round((float) $sales->sum('tax_amount'), 2);
        $returnsAmount = round((float) $returns->sum('refund_amount'), 2);
        $taxRefund = round($returns->sum(fn ($return) => $this->returnTax($return)), 2);
        $returnBase = round($returnsAmount - $taxRefund, 2);
        $netSales = round($salesNetBeforeReturns - $returnBase, 2);
        $taxCollected = round($salesTax - $taxRefund, 2);

        $salesCost = round($sales->sum(fn ($sale) => $sale->items->sum(
            fn ($item) => (float) $item->unit_cost * (int) $item->quantity
        )), 2);
        $restockedReturnCost = round($returns->sum(fn ($return) => $return->items
            ->where('is_restocked', true)
            ->sum(fn ($item) => (float) ($item->saleItem?->unit_cost ?? 0) * (int) $item->quantity)), 2);
        $totalCost = round($salesCost - $restockedReturnCost, 2);

        $collections = ['cash' => 0.0, 'card' => 0.0, 'momo' => 0.0, 'insurance' => 0.0];
        foreach ($sales as $sale) {
            $method = $sale->payment_type ?? 'unknown';
            $collections[$method] = ($collections[$method] ?? 0) + (float) $sale->total_amount;
        }
        foreach ($returns as $return) {
            $method = $return->refund_method ?? 'cash';
            $collections[$method] = ($collections[$method] ?? 0) - (float) $return->refund_amount;
        }

        $paymentCounts = $sales->countBy(fn ($sale) => $sale->payment_type ?? 'unknown');
        $paymentBreakdown = $paymentCounts->map(fn ($count) => $sales->isEmpty()
            ? 0 : round(($count / $sales->count()) * 100, 1))->all();
        $categoryCounts = $sales->flatMap->items->groupBy(fn ($item) => $item->drug?->drug_class ?? 'Uncategorized')
            ->map->sum('quantity');
        $categoryTotal = max(1, (int) $categoryCounts->sum());
        $categoryBreakdown = $categoryCounts->map(fn ($count) => round(($count / $categoryTotal) * 100, 1))->all();
        $userBreakdown = $sales->groupBy('user_id')->map(function ($userSales) {
            return [
                'name' => $userSales->first()?->user?->name ?? 'Unknown User',
                'total' => round((float) $userSales->sum('total_amount'), 2),
            ];
        })->values()->all();
        $uniqueCustomers = $sales->map(fn ($sale) => $sale->customers->first()?->id ?: 'anonymous:'.$sale->id)->unique()->count();

        $attributes = [
            'gross_sales' => $grossSales,
            'net_sales' => $netSales,
            'tax_collected' => $taxCollected,
            'total_discounts' => 0,
            'total_cost' => $totalCost,
            'gross_profit' => round($netSales - $totalCost, 2),
            'total_transactions' => $sales->count(),
            'prescription_count' => $sales->flatMap->items->where('requires_prescription', true)->sum('quantity'),
            'otc_count' => $sales->flatMap->items->where('requires_prescription', false)->sum('quantity'),
            'cash_collected' => round($collections['cash'] ?? 0, 2),
            'card_collected' => round($collections['card'] ?? 0, 2),
            'momo_collected' => round($collections['momo'] ?? 0, 2),
            'insurance_billed' => round($collections['insurance'] ?? 0, 2),
            'returns_amount' => $returnsAmount,
            'customer_count' => $uniqueCustomers,
            'payment_breakdown' => $paymentBreakdown,
            'category_breakdown' => $categoryBreakdown,
            'user_breakdown' => $userBreakdown,
        ];

        if ($persist) {
            $summary = FinancialDaySummary::firstOrNew([
                'account_id' => $accountId,
                'branch_id' => $branchId,
                'day' => $localDay->toDateString(),
            ]);
            if (!$summary->is_closed) {
                if (!$summary->exists) {
                    $summary->id = (string) Str::ulid();
                }
                $summary->fill($attributes)->save();
            }
        } else {
            $summary = new FinancialDaySummary($attributes + [
                'id' => (string) Str::ulid(),
                'account_id' => $accountId,
                'branch_id' => $branchId,
                'day' => $localDay->toDateString(),
            ]);
        }

        $transactions = $sales->map(function ($sale) {
            $cost = round($sale->items->sum(fn ($item) => (float) $item->unit_cost * (int) $item->quantity), 2);
            $profit = round((float) $sale->subtotal_amount - $cost, 2);
            $sale->setAttribute('total_cost', $cost);
            $sale->setAttribute('gross_profit', $profit);
            $sale->setAttribute('gross_margin', (float) $sale->subtotal_amount > 0
                ? round(($profit / (float) $sale->subtotal_amount) * 100, 2) : 0);
            return $sale;
        });
        $summary->setRelation('transactions', $transactions);
        $summary->setAttribute('return_transactions', $returns);
        $summary->setAttribute('tax_breakdown_details', $this->taxBreakdown($sales, $returns));

        return $summary;
    }

    public function exportRegulatoryData(string $startDate, string $endDate, ?string $branchId = null): string
    {
        $accountId = app(TenantContext::class)->getAccountId();
        if (!$accountId) {
            throw new RuntimeException('A pharmacy account must be selected before exporting.');
        }
        $branchId ??= app(DeviceContextService::class)->currentBranchId((string) $accountId);
        $branch = DB::table('branches')->where('account_id', $accountId)->where('branch_id', $branchId)->first();
        if (!$branch) {
            throw new RuntimeException('The selected export branch does not belong to this pharmacy.');
        }

        $timezone = $branch->timezone ?? config('app.timezone', 'UTC');
        $start = CarbonImmutable::parse($startDate, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($endDate, $timezone)->endOfDay()->utc();
        $sales = Sale::query()->where('branch_id', $branchId)
            ->whereBetween('finalized_at', [$start, $end])
            ->with(['items.drug', 'items.batch', 'customers', 'user'])->get();
        $returns = SalesReturn::query()->where('branch_id', $branchId)
            ->whereBetween('returned_at', [$start, $end])
            ->with(['items.saleItem.drug', 'items.saleItem.batch', 'sale.customers', 'user'])->get();

        $dir = storage_path('app/exports');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create the protected report export directory.');
        }
        $path = $dir.'/regulatory_report_'.Str::random(20).'.csv';
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to create the regulatory export.');
        }
        fputcsv($handle, [
            'Record Type', 'Reference ID', 'Sale ID', 'Branch', 'Date UTC', 'Customer', 'Served By', 'Drug', 'Strength',
            'Controlled', 'Prescription', 'Quantity', 'Unit Price', 'Unit Cost', 'Line Total',
            'Line Tax', 'Tax Breakdown', 'Payment Type', 'Batch/Lot', 'Expiry', 'Returned Amount', 'Reason',
        ]);
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                fputcsv($handle, $this->safeCsvRow([
                    'SALE', $sale->id, $sale->id, $branch->name, $sale->finalized_at?->utc()->toIso8601String(),
                    $sale->customers->first()?->name ?? 'N/A', $sale->user?->name ?? 'N/A',
                    $item->drug?->name ?? 'N/A', $item->drug?->strength ?? '',
                    $item->drug?->is_controlled || $item->drug?->is_narcotic ? 'Yes' : 'No',
                    $item->requires_prescription ? 'Yes' : 'No', $item->quantity, $item->price,
                    $item->unit_cost, $item->line_total, $item->tax_amount,
                    json_encode($item->tax_breakdown ?? [], JSON_UNESCAPED_SLASHES),
                    $sale->payment_type, $item->batch?->lot_number ?? 'N/A',
                    $item->batch?->expiry_date?->toDateString() ?? 'N/A', 0, '',
                ]));
            }
        }
        foreach ($returns as $return) {
            foreach ($return->items as $returnItem) {
                $item = $returnItem->saleItem;
                if (!$item) continue;
                $taxRefund = $item->quantity > 0
                    ? round(((int) $returnItem->quantity / (int) $item->quantity) * (float) $item->tax_amount, 2)
                    : 0;
                fputcsv($handle, $this->safeCsvRow([
                    'RETURN', $return->id, $return->sale_id, $branch->name, $return->returned_at?->utc()->toIso8601String(),
                    $return->sale?->customers->first()?->name ?? 'N/A', $return->user?->name ?? 'N/A',
                    $item->drug?->name ?? 'N/A', $item->drug?->strength ?? '',
                    $item->drug?->is_controlled || $item->drug?->is_narcotic ? 'Yes' : 'No',
                    $item->requires_prescription ? 'Yes' : 'No', -1 * (int) $returnItem->quantity,
                    $item->price, $item->unit_cost, -1 * ((float) $returnItem->refund_amount - $taxRefund),
                    -1 * $taxRefund, json_encode($item->tax_breakdown ?? [], JSON_UNESCAPED_SLASHES),
                    $return->refund_method, $item->batch?->lot_number ?? 'N/A',
                    $item->batch?->expiry_date?->toDateString() ?? 'N/A', $returnItem->refund_amount, $return->reason,
                ]));
            }
        }
        fclose($handle);

        return $path;
    }

    private function returnTax(SalesReturn $return): float
    {
        return round($return->items->sum(function ($item) {
            $saleItem = $item->saleItem;
            return $saleItem && $saleItem->quantity > 0
                ? ((int) $item->quantity / (int) $saleItem->quantity) * (float) $saleItem->tax_amount
                : 0;
        }), 2);
    }

    private function taxBreakdown($sales, $returns): array
    {
        $breakdown = [];
        foreach ($sales->pluck('tax_breakdown')->filter()->flatten(1) as $tax) {
            $key = $tax['tax_rate_id'] ?? $tax['tax_name'];
            if (!isset($breakdown[$key])) {
                $breakdown[$key] = $tax;
                $breakdown[$key]['tax_amount'] = 0;
            }
            $breakdown[$key]['tax_amount'] = round((float) ($breakdown[$key]['tax_amount'] ?? 0) + (float) $tax['tax_amount'], 2);
        }
        // Return rows retain line snapshots, so subtract their proportional tax.
        foreach ($returns as $return) {
            foreach ($return->items as $item) {
                $saleItem = $item->saleItem;
                if (!$saleItem || !$saleItem->quantity) continue;
                foreach ($saleItem->tax_breakdown ?? [] as $tax) {
                    $key = $tax['tax_rate_id'] ?? $tax['tax_name'];
                    if (!isset($breakdown[$key])) continue;
                    $refund = ((int) $item->quantity / (int) $saleItem->quantity) * (float) $tax['tax_amount'];
                    $breakdown[$key]['tax_amount'] = round((float) $breakdown[$key]['tax_amount'] - $refund, 2);
                }
            }
        }
        return array_values($breakdown);
    }

    private function safeCsvRow(array $row): array
    {
        return array_map(static function ($value) {
            if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
                return "'".$value;
            }
            return $value;
        }, $row);
    }
}
