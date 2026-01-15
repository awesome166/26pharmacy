<?php

namespace App\Services;

/**
 * Service for generating financial and regulatory reports.
 */
class ReportingService
{
    /**
     * Generate a daily financial summary for a branch.
     *
     * @param string $accountid
     * @param string $date
     * @return object
     */
    public function getDailySummary(string $accountid, string $date)
    {
        $summary = \Illuminate\Support\Facades\DB::table('financial_day_summaries')
            ->where('account_id', $accountid)
            ->where('day', $date)
            ->first();

        if (!$summary) {
            $totals = \Illuminate\Support\Facades\DB::table('sales')
                ->where('account_id', $accountid)
                ->whereDate('finalized_at', $date)
                ->selectRaw('SUM(total_amount) as gross_sales, SUM(tax_amount) as tax_collected')
                ->first();

            $id = \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\DB::table('financial_day_summaries')->insert([
                'summary_id' => $id,
                'account_id' => $accountid,
                'day' => $date,
                'gross_sales' => $totals->gross_sales ?? 0,
                'tax_collected' => $totals->tax_collected ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) ['gross_sales' => $totals->gross_sales, 'tax_collected' => $totals->tax_collected];
        }

        return $summary;
    }

    /**
     * Export regulatory sales data.
     *
     * @param string $tenantId
     * @param string $startDate
     * @param string $endDate
     * @return string Path to exported file
     */
    public function exportRegulatoryData(string $tenantId, string $startDate, string $endDate)
    {
        // Logic to generate heavy export would go here (e.g., using FastExcel)
        return "storage/exports/regulatory_report_{$tenantId}.csv";
    }
}
