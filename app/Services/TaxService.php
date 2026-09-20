<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\TaxRate;
use Carbon\CarbonImmutable;

/**
 * Service for tax calculations and jurisdiction management.
 */
class TaxService
{
    /**
     * Calculate tax for an amount based on jurisdiction.
     *
     * @param float $amount
     * @param string $jurisdiction
     * @param string|null $taxName
     * @return array [tax_amount, total_amount]
     */
    public function calculateTax(float $amount, string $jurisdiction, ?string $taxName = null)
    {
        $result = $this->calculateLine($amount, null, $jurisdiction, $taxName);
        $first = $result['breakdown'][0] ?? null;

        return [
            'tax_amount' => $result['tax_amount'],
            'total' => $result['total'],
            'tax_rate_id' => $first['tax_rate_id'] ?? null,
            'tax_name' => $first['tax_name'] ?? null,
            'tax_type' => $first['tax_type'] ?? null,
            'breakdown' => $result['breakdown'],
        ];
    }

    /**
     * Calculate every applicable levy for a line. Tenant rates override a
     * global rate with the same jurisdiction/name, while distinct levies stack.
     */
    public function calculateLine(
        float $amount,
        ?string $category = null,
        ?string $jurisdiction = null,
        ?string $taxName = null,
        ?CarbonImmutable $at = null,
    ): array {
        $amount = round(max(0, $amount), 2);
        $rates = $this->activeRates($jurisdiction, $taxName, $at);
        $taxTotal = 0.0;
        $breakdown = [];

        foreach ($rates as $rate) {
            if (!$this->appliesToCategory($rate, $category) || !$this->appliesToBracket($rate, $amount)) {
                continue;
            }

            $taxableBase = $amount + ($rate->is_compound ? $taxTotal : 0);
            $taxAmount = round($taxableBase * ((float) $rate->percentage / 100), 2);
            $taxTotal = round($taxTotal + $taxAmount, 2);
            $breakdown[] = [
                'tax_rate_id' => (string) $rate->id,
                'tax_name' => $rate->tax_name,
                'tax_type' => $rate->tax_type,
                'jurisdiction' => $rate->jurisdiction,
                'percentage' => (float) $rate->percentage,
                'taxable_base' => round($taxableBase, 2),
                'tax_amount' => $taxAmount,
            ];
        }

        return [
            'tax_amount' => $taxTotal,
            'total' => round($amount + $taxTotal, 2),
            'breakdown' => $breakdown,
        ];
    }

    public function calculateSaleLines(array $lines, ?string $jurisdiction = null): array
    {
        if (!(bool) SystemSetting::getValue('sales_add_tax', false)) {
            return collect($lines)->map(fn ($line) => [
                'key' => $line['key'], 'tax_amount' => 0.0, 'breakdown' => [],
            ])->values()->all();
        }

        return collect($lines)->map(function ($line) use ($jurisdiction) {
            $tax = $this->calculateLine(
                (float) $line['amount'],
                $line['category'] ?? null,
                $jurisdiction,
            );

            return [
                'key' => $line['key'],
                'tax_amount' => $tax['tax_amount'],
                'breakdown' => $tax['breakdown'],
            ];
        })->values()->all();
    }

    public function activeRates(
        ?string $jurisdiction = null,
        ?string $taxName = null,
        ?CarbonImmutable $at = null,
    ) {
        $at ??= CarbonImmutable::now();
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        $query = TaxRate::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $at->toDateString())
            ->where(function ($query) use ($at) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $at->toDateString());
            })
            ->where(function ($query) use ($accountId) {
                $query->whereNull('account_id');
                if ($accountId) {
                    $query->orWhere('account_id', $accountId);
                }
            });

        if ($jurisdiction) {
            $query->where('jurisdiction', $jurisdiction);
        }
        if ($taxName) {
            $query->where('tax_name', $taxName);
        }

        $rates = $query->orderBy('calculation_order')
            ->orderByRaw('account_id IS NOT NULL DESC')
            ->orderByDesc('effective_from')
            ->get();

        return $rates->groupBy(fn (TaxRate $rate) => mb_strtolower($rate->jurisdiction.'|'.$rate->tax_name))
            ->flatMap(function ($group) use ($accountId) {
                $tenantRates = $accountId
                    ? $group->where('account_id', $accountId)
                    : collect();
                return $tenantRates->isNotEmpty() ? $tenantRates : $group->whereNull('account_id');
            })
            ->sortBy([['calculation_order', 'asc'], ['tax_name', 'asc']])
            ->values();
    }

    protected function appliesToCategory(TaxRate $rate, ?string $category): bool
    {
        $categories = collect($rate->applicable_categories ?? [])
            ->map(fn ($value) => mb_strtolower(trim((string) $value)));

        return $categories->isEmpty()
            || $categories->contains('all')
            || ($category !== null && $categories->contains(mb_strtolower(trim($category))));
    }

    protected function appliesToBracket(TaxRate $rate, float $amount): bool
    {
        return ($rate->minimum_taxable_amount === null || $amount >= (float) $rate->minimum_taxable_amount)
            && ($rate->maximum_taxable_amount === null || $amount <= (float) $rate->maximum_taxable_amount);
    }

    /**
     * Update tax rates in the system.
     *
     * @param array $taxData
     * @return void
     */
    public function updateTaxRates(array $taxData)
    {
        // Use TaxRate model - account_id handled automatically by UsesTenant trait
        // unless explicitly provided (for global rates with account_id = null)
        \App\Models\TaxRate::create([
            'id' => \Illuminate\Support\Str::ulid(),
            'account_id' => $taxData['account_id'] ?? null, // Allow explicit null for global rates
            'jurisdiction' => $taxData['jurisdiction'],
            'tax_name' => $taxData['tax_name'] ?? 'Sales Tax',
            'percentage' => $taxData['percentage'],
            'tax_type' => $taxData['tax_type'] ?? 'sales',
            'applicable_categories' => $taxData['applicable_categories'] ?? null,
            'description' => $taxData['description'] ?? null,
            'effective_from' => $taxData['effective_from'],
            'effective_to' => $taxData['effective_to'] ?? null,
            'is_active' => $taxData['is_active'] ?? true,
        ]);
    }
}
