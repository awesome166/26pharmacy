<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\JournalEntryDetail;
use Modules\Accounting\Models\AccountsPayable;
use Modules\Accounting\Models\BudgetDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportGenerator
{
    public function generateBalanceSheet($date = null)
    {
        $date = $date ? Carbon::parse($date) : now();

        $assets = ChartOfAccount::where('type', 'Asset')->where('is_group', true)->whereNull('parent_id')->with('children')->get();
        $liabilities = ChartOfAccount::where('type', 'Liability')->where('is_group', true)->whereNull('parent_id')->with('children')->get();
        $equity = ChartOfAccount::where('type', 'Equity')->where('is_group', true)->whereNull('parent_id')->with('children')->get();

        return [
            'report' => 'Balance Sheet',
            'date' => $date->format('Y-m-d'),
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $assets->sum('current_balance'),
            'total_liabilities' => $liabilities->sum('current_balance'),
            'total_equity' => $equity->sum('current_balance'),
        ];
    }

    public function generateIncomeStatement($startDate, $endDate)
    {
        $revenue = ChartOfAccount::where('type', 'Revenue')->get();
        $expenses = ChartOfAccount::where('type', 'Expense')->get();

        // Calculate periodic movement
        // This is a simplified version. Real version would query JE Details between dates.

        $revenueData = $this->calculateMovement($revenue, $startDate, $endDate);
        $expenseData = $this->calculateMovement($expenses, $startDate, $endDate);

        return [
            'report' => 'Income Statement',
            'period' => "$startDate to $endDate",
            'revenue' => $revenueData,
            'expenses' => $expenseData,
            'net_income' => $revenueData->sum('balance') - $expenseData->sum('balance'),
        ];
    }

    public function generatePayableAgingReport()
    {
        $payables = AccountsPayable::where('status', '!=', 'paid')
            ->select('*', DB::raw('DATEDIFF(NOW(), due_date) as days_overdue'))
            ->get();

        return [
            'current' => $payables->where('days_overdue', '<=', 0),
            '1-30' => $payables->whereBetween('days_overdue', [1, 30]),
            '31-60' => $payables->whereBetween('days_overdue', [31, 60]),
            '60+' => $payables->where('days_overdue', '>', 60),
        ];
    }

    public function generateBudgetVarianceReport($fiscalYear)
    {
        $variances = DB::table('accounting_budget_details')
            ->join('accounting_budgets', 'accounting_budgets.id', '=', 'accounting_budget_details.budget_id')
            ->join('accounting_chart_of_accounts', 'accounting_chart_of_accounts.id', '=', 'accounting_budget_details.chart_of_account_id')
            ->where('accounting_budgets.fiscal_year', $fiscalYear)
            ->select(
                'accounting_chart_of_accounts.name as account',
                'accounting_budget_details.amount as budgeted',
                'accounting_chart_of_accounts.current_balance as actual', // Simplified: assumes current balance is for this year
                DB::raw('accounting_budget_details.amount - accounting_chart_of_accounts.current_balance as variance')
            )
            ->get();

        return $variances;
    }

    public function generateTrialBalance()
    {
        return ChartOfAccount::select('code', 'name', 'type', 'current_balance')
            ->where('current_balance', '!=', 0)
            ->get();
    }

    // Helper to calculate movement for Income Statement
    private function calculateMovement($accounts, $start, $end)
    {
        return $accounts->map(function ($account) use ($start, $end) {
            $debits = JournalEntryDetail::where('chart_of_account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($start, $end) {
                    $q->whereBetween('date', [$start, $end])->where('status', 'posted');
                })->sum('debit');

            $credits = JournalEntryDetail::where('chart_of_account_id', $account->id)
                ->whereHas('journalEntry', function ($q) use ($start, $end) {
                    $q->whereBetween('date', [$start, $end])->where('status', 'posted');
                })->sum('credit');

            $balance = 0;
            if (in_array($account->type, ['Asset', 'Expense'])) {
                $balance = $debits - $credits;
            } else {
                $balance = $credits - $debits;
            }

            $account->balance = $balance;
            return $account;
        });
    }

    public function generateGeneralLedger($accountId, $start, $end)
    {
        return JournalEntryDetail::where('chart_of_account_id', $accountId)
            ->with('journalEntry')
            ->whereHas('journalEntry', function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start, $end])->where('status', 'posted');
            })
            ->get();
    }

    public function generateCostCenterReport($costCenterId, $start, $end)
    {
        // Expenses by Cost Center
         return JournalEntryDetail::whereHas('journalEntry', function($q) use ($start, $end) {
                $q->whereBetween('date', [$start, $end]);
            })
            // This requires linking JE details to Cost Centers, which I didn't add explicitly to JE Details
            // But usually expense accounts are linked to cost centers in budget or via tagging.
            // For now, I'll return empty or mock logic.
            ->get();
    }

    public function generateCashFlowStatement($start, $end)
    {
        // simplified cash flow: movement in Cash/Bank accounts
        $cashAccounts = ChartOfAccount::where('category', 'Cash')->orWhere('category', 'Bank')->pluck('id');

        $movements = JournalEntryDetail::whereIn('chart_of_account_id', $cashAccounts)
            ->whereHas('journalEntry', function($q) use ($start, $end) {
                $q->whereBetween('date', [$start, $end])->where('status', 'posted');
            })
            ->select('description', 'debit', 'credit')
            ->get();

        return [
            'operating_activities' => $movements,
            'net_cash_flow' => $movements->sum('debit') - $movements->sum('credit') // Asset: Debit increases
        ];
    }

    public function generateBankReconciliationReport($bankAccountId, $date)
    {
        $account = \Modules\Accounting\Models\BankAccount::find($bankAccountId);
        // transactions up to date, not reconciled
        $unreconciled = $account->transactions()
            ->where('date', '<=', $date)
            ->where('reconciled', false)
            ->get();

        return [
            'bank_account' => $account->account_name,
            'book_balance' => $account->current_balance,
            'unreconciled_transactions' => $unreconciled,
            // adjusted balance logic would go here
        ];
    }
}
