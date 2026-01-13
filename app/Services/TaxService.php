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
    public function calculateTax(float $amount, string $jurisdiction)
    {
        $rate = \Illuminate\Support\Facades\DB::table('tax_rates')
            ->where('jurisdiction', $jurisdiction)
            ->where('effective_from', '<=', now())
            ->orderBy('effective_from', 'desc')
            ->first();

        $percentage = $rate ? $rate->percentage : 0;
        $taxAmount = $amount * ($percentage / 100);

        return [
            'tax_amount' => round($taxAmount, 2),
            'total' => round($amount + $taxAmount, 2)
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
            'tax_rate_id' => \Illuminate\Support\Str::uuid(),
            'jurisdiction' => $taxData['jurisdiction'],
            'percentage' => $taxData['percentage'],
            'effective_from' => $taxData['effective_from'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
