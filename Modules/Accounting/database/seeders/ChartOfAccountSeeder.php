<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\ChartOfAccount;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Assets
        $assets = ChartOfAccount::create([
            'code' => '1000', 'name' => 'Assets', 'type' => 'Asset', 'is_group' => true
        ]);

        // Current Assets
        $currentAssets = ChartOfAccount::create([
            'code' => '1100', 'name' => 'Current Assets', 'type' => 'Asset', 'parent_id' => $assets->id, 'is_group' => true
        ]);

        ChartOfAccount::create(['code' => '1110', 'name' => 'Cash on Hand', 'type' => 'Asset', 'category' => 'Cash', 'parent_id' => $currentAssets->id]);
        ChartOfAccount::create(['code' => '1120', 'name' => 'Bank - Operating', 'type' => 'Asset', 'category' => 'Bank', 'parent_id' => $currentAssets->id, 'is_bank' => true]);
        ChartOfAccount::create(['code' => '1130', 'name' => 'Accounts Receivable', 'type' => 'Asset', 'category' => 'Receivable', 'parent_id' => $currentAssets->id]);
        ChartOfAccount::create(['code' => '1140', 'name' => 'Inventory - Pharmacy', 'type' => 'Asset', 'category' => 'Inventory', 'parent_id' => $currentAssets->id]);

        // Non-Current Assets
        $fixedAssets = ChartOfAccount::create([
            'code' => '1200', 'name' => 'Non-Current Assets', 'type' => 'Asset', 'parent_id' => $assets->id, 'is_group' => true
        ]);
        ChartOfAccount::create(['code' => '1210', 'name' => 'Medical Equipment', 'type' => 'Asset', 'category' => 'Equipment', 'parent_id' => $fixedAssets->id]);
        ChartOfAccount::create(['code' => '1220', 'name' => 'Buildings', 'type' => 'Asset', 'category' => 'Property', 'parent_id' => $fixedAssets->id]);

        // Liabilities
        $liabilities = ChartOfAccount::create([
            'code' => '2000', 'name' => 'Liabilities', 'type' => 'Liability', 'is_group' => true
        ]);

        $currentLiabilities = ChartOfAccount::create([
            'code' => '2100', 'name' => 'Current Liabilities', 'type' => 'Liability', 'parent_id' => $liabilities->id, 'is_group' => true
        ]);
        ChartOfAccount::create(['code' => '2110', 'name' => 'Accounts Payable', 'type' => 'Liability', 'category' => 'Payable', 'parent_id' => $currentLiabilities->id]);
        ChartOfAccount::create(['code' => '2120', 'name' => 'Salaries Payable', 'type' => 'Liability', 'category' => 'Payable', 'parent_id' => $currentLiabilities->id]);

        // Equity
        $equity = ChartOfAccount::create([
            'code' => '3000', 'name' => 'Equity', 'type' => 'Equity', 'is_group' => true
        ]);
        ChartOfAccount::create(['code' => '3100', 'name' => 'Capital', 'type' => 'Equity', 'parent_id' => $equity->id]);
        ChartOfAccount::create(['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'Equity', 'parent_id' => $equity->id]);

        // Revenue
        $revenue = ChartOfAccount::create([
            'code' => '4000', 'name' => 'Revenue', 'type' => 'Revenue', 'is_group' => true
        ]);
        ChartOfAccount::create(['code' => '4100', 'name' => 'Patient Services Revenue', 'type' => 'Revenue', 'parent_id' => $revenue->id]);
        ChartOfAccount::create(['code' => '4200', 'name' => 'Pharmacy Sales', 'type' => 'Revenue', 'parent_id' => $revenue->id]);
        ChartOfAccount::create(['code' => '4300', 'name' => 'Lab Fees', 'type' => 'Revenue', 'parent_id' => $revenue->id]);

        // Expenses
        $expense = ChartOfAccount::create([
            'code' => '5000', 'name' => 'Expenses', 'type' => 'Expense', 'is_group' => true
        ]);
        ChartOfAccount::create(['code' => '5100', 'name' => 'Medical Supplies', 'type' => 'Expense', 'parent_id' => $expense->id]);
        ChartOfAccount::create(['code' => '5200', 'name' => 'Staff Salaries', 'type' => 'Expense', 'parent_id' => $expense->id]);
        ChartOfAccount::create(['code' => '5300', 'name' => 'Utilities', 'type' => 'Expense', 'parent_id' => $expense->id]);
        ChartOfAccount::create(['code' => '5400', 'name' => 'Maintenance', 'type' => 'Expense', 'parent_id' => $expense->id]);
    }
}
