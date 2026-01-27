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
     * @param string|null $taxName
     * @return array [tax_amount, total_amount]
     */
    public function calculateTax(float $amount, string $jurisdiction, ?string $taxName = null)
    {
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        // Use TaxRate model, but bypass global scope to access both tenant and global rates
        $query = \App\Models\TaxRate::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
            ->where('jurisdiction', $jurisdiction)
            ->where('effective_from', '<=', now())
            ->where('is_active', true)
            ->where(function($q) use ($accountId) {
                if ($accountId) {
                    // Prioritize tenant-specific rates, fallback to global
                    $q->where('account_id', $accountId)
                      ->orWhereNull('account_id');
                } else {
                    // Only global rates when no account context
                    $q->whereNull('account_id');
                }
            });

        if ($taxName) {
            $query->where('tax_name', $taxName);
        }

        // Order to prioritize tenant-specific over global, then by effective date
        $rate = $query->orderByRaw('account_id IS NOT NULL DESC')
                      ->orderBy('effective_from', 'desc')
                      ->first();

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
