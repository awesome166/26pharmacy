<?php

namespace App\Services;

/**
 * Service for generating financial and regulatory reports.
 */
class ReportingService
{
    /**
     * Generate a daily financial summary for the current tenant account.
     *
     * @param string $date
     * @return object|null
     */
    public function getDailySummary(string $date)
    {
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $isGlobal = empty($accountId);

        // Fetch sales. If global, bypass tenant scope to fetch ALL sales.
        $query = \App\Models\Sale::with(['items.inventory', 'items.drug', 'user', 'customers'])
            ->whereDate('finalized_at', $date);

        if ($isGlobal) {
            $query->withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class);
        }

        $sales = $query->get();

        $grossSales = 0;
        $netSales = 0;
        $taxCollected = 0;
        $totalDiscounts = 0;
        $totalCost = 0;
        $totalTransactions = $sales->count();
        $prescriptionCount = 0;
        $otcCount = 0;
        $cashCollected = 0;
        $cardCollected = 0;
        $momoCollected = 0;
        $insuranceBilled = 0;
        $returnsAmount = 0;
        $customerCount = $sales->count();

        $paymentCounts = [];
        $categoryCounts = [];
        $paymentCounts = [];
        $categoryCounts = [];
        $uniqueCustomers = [];
        $userSales = [];

        foreach ($sales as $sale) {
            // According to User's data model: Total = Gross? Or Total = Net + Tax?
            // Usually Gross = Revenue before deductions. User JSON has Gross < Net which is extremely weird.
            // We will stick to standard: Gross = Total Amount, Net = Subtotal.
            // If User's system implies otherwise, they need to clarify.
            // Based on typical POS: Total = Subtotal + Tax.
            $grossSales += $sale->total_amount;

            // Fix: If subtotal is 0 but we have total/tax, imply it
            $currentNet = $sale->subtotal_amount;
            if ($currentNet == 0 && $sale->total_amount != 0) {
                 $currentNet = $sale->total_amount - $sale->tax_amount;
                 \Log::info("Healed Net for Sale {$sale->id}: Old Subtotal={$sale->subtotal_amount}, New Net={$currentNet}");
            }
            $netSales += $currentNet;

            $taxCollected += $sale->tax_amount;

             // Infer Discount: If (Subtotal + Tax) > Total, difference is discount
            $theoreticalTotal = $currentNet + $sale->tax_amount;
            if ($theoreticalTotal > $sale->total_amount) {
                // Check floating point variance
                $diff = $theoreticalTotal - $sale->total_amount;
                if ($diff > 0.001) {
                    $totalDiscounts += $diff;
                }
            }

            // Infer Returns: Negative total implies a refund
            if ($sale->total_amount < 0) {
                $returnsAmount += abs($sale->total_amount);
            }

            // Unique Customer Counting
            if ($sale->customer_email) $uniqueCustomers['email:'.$sale->customer_email] = true;
            elseif ($sale->customer_phone) $uniqueCustomers['phone:'.$sale->customer_phone] = true;
            elseif ($sale->customer_name) $uniqueCustomers['name:'.$sale->customer_name] = true;
            else $uniqueCustomers['anon:'.$sale->id] = true;


            $pType = $sale->payment_type ?? 'unknown';
            $paymentCounts[$pType] = ($paymentCounts[$pType] ?? 0) + 1;

            if ($pType === 'cash') $cashCollected += $sale->total_amount;
            elseif ($pType === 'card') $cardCollected += $sale->total_amount;
            elseif ($pType === 'momo') $momoCollected += $sale->total_amount;
            elseif ($pType === 'insurance') $insuranceBilled += $sale->total_amount;

            foreach ($sale->items as $item) {
                if ($item->requires_prescription) {
                    $prescriptionCount += $item->quantity;
                } else {
                    $otcCount += $item->quantity;
                }

                $itemCost = ($item->inventory->cost_price ?? 0) * $item->quantity;
                $totalCost += $itemCost;

                $category = $item->drug->drug_class ?? 'Uncategorized';
                $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + $item->quantity;
            }


            // User Breakdown
            $userName = $sale->user ? $sale->user->name : 'Unknown User';
            // Use User ID as key to prevent name collision, store name and total
            if (!isset($userSales[$sale->user_id])) {
                $userSales[$sale->user_id] = [
                    'name' => $userName,
                    'total' => 0
                ];
            }
            $userSales[$sale->user_id]['total'] += $sale->total_amount;
        }

        $grossProfit = $netSales - $totalCost;

        $totalPaymentEvents = array_sum($paymentCounts);
        $paymentBreakdown = [];
        if ($totalPaymentEvents > 0) {
            foreach ($paymentCounts as $type => $count) {
                $paymentBreakdown[$type] = round(($count / $totalPaymentEvents) * 100, 1);
            }
        }

        $totalCategoryItems = array_sum($categoryCounts);
        $categoryBreakdown = [];
        if ($totalCategoryItems > 0) {
            foreach ($categoryCounts as $cat => $count) {
                $categoryBreakdown[$cat] = round(($count / $totalCategoryItems) * 100, 1);
            }
        }

        try {
            if ($isGlobal) {
                // Return transient object for global report
                $summary = new \App\Models\FinancialDaySummary();
                $summary->day = \Carbon\Carbon::parse($date)->format('Y-m-d');
            } else {
                $summary = \App\Models\FinancialDaySummary::firstOrNew(['day' => \Carbon\Carbon::parse($date)->format('Y-m-d')]);
            }

            $summary->gross_sales = $grossSales;
            $summary->net_sales = $netSales;
            $summary->tax_collected = $taxCollected;
            $summary->total_discounts = $totalDiscounts;
            $summary->total_cost = $totalCost;
            $summary->gross_profit = $grossProfit;
            $summary->total_transactions = $totalTransactions;
            $summary->prescription_count = $prescriptionCount;
            $summary->otc_count = $otcCount;
            $summary->cash_collected = $cashCollected;
            $summary->card_collected = $cardCollected;
            $summary->momo_collected = $momoCollected;
            $summary->insurance_billed = $insuranceBilled;
            $summary->returns_amount = $returnsAmount;
            $summary->customer_count = count($uniqueCustomers) > 0 ? count($uniqueCustomers) : $totalTransactions;
            $summary->payment_breakdown = $paymentBreakdown;
            $summary->payment_breakdown = $paymentBreakdown;
            $summary->category_breakdown = $categoryBreakdown;
            $summary->user_breakdown = array_values($userSales); // Convert to array list for JSON chart/table friendly structure

            if (!$isGlobal) {
                if (!$summary->exists) {
                    $summary->id = (string) \Illuminate\Support\Str::ulid();
                }
                \Log::info("Saving Summary for {$date} (Account: {$accountId}): Net=\${$netSales}, Gross=\${$grossSales}, Momo=\${$momoCollected}");
                $summary->save();
            }

            // Attach transaction records with per-sale cost/profit fields.
            $transactions = $sales->map(function ($sale) {
                $totalCost = 0.0;
                foreach ($sale->items as $item) {
                    $costPrice = (float) ($item->inventory->cost_price ?? 0);
                    $totalCost += $costPrice * (float) $item->quantity;
                }

                $netSales = (float) ($sale->subtotal_amount ?? 0);
                if ($netSales <= 0) {
                    $netSales = max(0, (float) $sale->total_amount - (float) $sale->tax_amount);
                }

                $grossProfit = round($netSales - $totalCost, 2);
                $margin = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0.0;

                $sale->setAttribute('total_cost', round($totalCost, 2));
                $sale->setAttribute('gross_profit', $grossProfit);
                $sale->setAttribute('gross_margin', $margin);

                return $sale;
            });

            $summary->setRelation('transactions', $transactions);

            return $summary;

        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return \App\Models\FinancialDaySummary::where('day', $date)->first();
            }
            throw $e;
        }
    }

    /**
     * Export regulatory sales data.
     *
     * @param string $tenantId
     * @param string $startDate
     * @param string $endDate
     * @return string Path to exported file
     */
    public function exportRegulatoryData(string $startDate, string $endDate)
    {
        $tenantId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        // Logic to generate heavy export would go here (e.g., using FastExcel)
        return "storage/exports/regulatory_report_{$tenantId}.csv";
    }
}
