<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankTransaction;
use Modules\Accounting\Models\ChartOfAccount;
use Carbon\Carbon;

class SampleBankAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $bankGL = ChartOfAccount::where('code', '1120')->first();

        $operating = BankAccount::create([
            'account_name' => 'Operating Account',
            'account_number' => '1234567890',
            'bank_name' => 'City Bank',
            'currency' => 'USD',
            'chart_of_account_id' => $bankGL->id,
            'opening_balance' => 50000.00,
            'current_balance' => 55000.00,
            'opening_date' => Carbon::now()->subYear(),
        ]);

        BankTransaction::create([
            'bank_account_id' => $operating->id,
            'date' => Carbon::now()->subDays(5),
            'amount' => 5000.00,
            'type' => 'deposit',
            'reference' => 'DEP-001',
            'description' => 'Initial Deposit',
            'reconciled' => true,
            'reconciled_at' => Carbon::now()->subDays(2),
        ]);
    }
}
