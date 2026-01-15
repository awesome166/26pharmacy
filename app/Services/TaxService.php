<?php

namespace App\Services;

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
     * @return array [tax_amount, total_amount]
     */
    public function calculateTax(float $amount, string $jurisdiction, ?string $taxName = null, ?string $accountId = null)
    {
        $query = \Illuminate\Support\Facades\DB::table('tax_rates')
            ->where('jurisdiction', $jurisdiction)
            ->where('effective_from', '<=', now())
            ->where('is_active', true);
        if ($taxName) {
            $query->where('tax_name', $taxName);
        }
        if ($accountId) {
            $query->where(function($q) use ($accountId) {
                $q->where('account_id', $accountId)->orWhereNull('account_id');
            });
        }
        $rate = $query->orderBy('effective_from', 'desc')->first();

        $percentage = $rate ? $rate->percentage : 0;
        $taxAmount = $amount * ($percentage / 100);

        return [
            'tax_amount' => round($taxAmount, 2),
            'total' => round($amount + $taxAmount, 2),
            'tax_rate_id' => $rate->id ?? null,
            'tax_name' => $rate->tax_name ?? null,
            'tax_type' => $rate->tax_type ?? null
        ];
    }

    /**
     * Update tax rates in the system.
     *
     * @param array $taxData
     * @return void
     */
    public function updateTaxRates(array $taxData)
    {
        \Illuminate\Support\Facades\DB::table('tax_rates')->insert([
            'id' => \Illuminate\Support\Str::uuid(),
            'account_id' => $taxData['account_id'] ?? null,
            'jurisdiction' => $taxData['jurisdiction'],
            'tax_name' => $taxData['tax_name'] ?? 'Sales Tax',
            'percentage' => $taxData['percentage'],
            'tax_type' => $taxData['tax_type'] ?? 'sales',
            'applicable_categories' => $taxData['applicable_categories'] ?? null,
            'description' => $taxData['description'] ?? null,
            'effective_from' => $taxData['effective_from'],
            'effective_to' => $taxData['effective_to'] ?? null,
            'is_active' => $taxData['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
