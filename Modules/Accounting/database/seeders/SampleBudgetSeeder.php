<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetDetail;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\CostCenter;
use Carbon\Carbon;

class SampleBudgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $budget = Budget::create([
            'fiscal_year' => '2024',
            'name' => 'Annual Operating Budget 2024',
            'start_date' => Carbon::create(2024, 1, 1),
            'end_date' => Carbon::create(2024, 12, 31),
            'status' => 'approved',
            'description' => 'Main hospital budget',
            'approved_at' => Carbon::now()->subMonths(2),
        ]);

        $supplies = ChartOfAccount::where('code', '5100')->first();
        $salaries = ChartOfAccount::where('code', '5200')->first();

        $clinical = CostCenter::where('code', 'CLIN')->first();
        $admin = CostCenter::where('code', 'ADMIN')->first();

        // Clinical Supplies Budget (Annual)
        BudgetDetail::create([
            'budget_id' => $budget->id,
            'cost_center_id' => $clinical->id,
            'chart_of_account_id' => $supplies->id,
            'period' => 'annual',
            'amount' => 120000.00, // 10k per month
        ]);

        // Admin Salaries Budget (Annual)
        BudgetDetail::create([
            'budget_id' => $budget->id,
            'cost_center_id' => $admin->id,
            'chart_of_account_id' => $salaries->id,
            'period' => 'annual',
            'amount' => 500000.00,
        ]);
    }
}
