<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;

class AccountingDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            ChartOfAccountSeeder::class,
            CostCenterSeeder::class,
            SampleJournalEntriesSeeder::class,
            SampleAccountsPayableSeeder::class,
            SampleBudgetSeeder::class,
            SampleBankAccountsSeeder::class,
        ]);
    }
}
