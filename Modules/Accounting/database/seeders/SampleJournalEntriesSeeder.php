<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\JournalEntryDetail;
use Modules\Accounting\Models\ChartOfAccount;
use Carbon\Carbon;

class SampleJournalEntriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cash = ChartOfAccount::where('code', '1110')->first()->id;
        $bank = ChartOfAccount::where('code', '1120')->first()->id;
        $suppliesExpense = ChartOfAccount::where('code', '5100')->first()->id;
        $patientRevenue = ChartOfAccount::where('code', '4100')->first()->id;
        $salaryExpense = ChartOfAccount::where('code', '5200')->first()->id;
        $ap = ChartOfAccount::where('code', '2110')->first()->id;

        // Transaction 1: Purchase Medical Supplies (Cash)
        $entry1 = JournalEntry::create([
            'entry_number' => 'JE-2024-001',
            'date' => Carbon::now()->subDays(10),
            'description' => 'Purchase of medical supplies',
            'status' => 'posted',
            'type' => 'purchase',
            'posted_at' => Carbon::now()->subDays(10),
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entry1->id,
            'chart_of_account_id' => $suppliesExpense,
            'debit' => 5000.00,
            'credit' => 0.00,
            'description' => 'Surgical masks and gloves'
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entry1->id,
            'chart_of_account_id' => $cash,
            'debit' => 0.00,
            'credit' => 5000.00,
            'description' => 'Cash payment'
        ]);

        // Transaction 2: Patient Service Revenue (Bank Transfer)
        $entry2 = JournalEntry::create([
            'entry_number' => 'JE-2024-002',
            'date' => Carbon::now()->subDays(5),
            'description' => 'Daily patient services revenue',
            'status' => 'posted',
            'type' => 'receipt',
            'posted_at' => Carbon::now()->subDays(5),
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entry2->id,
            'chart_of_account_id' => $bank,
            'debit' => 12500.00,
            'credit' => 0.00,
            'description' => 'Bank deposit'
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entry2->id,
            'chart_of_account_id' => $patientRevenue,
            'debit' => 0.00,
            'credit' => 12500.00,
            'description' => 'Consultation fees'
        ]);

        // Transaction 3: Salary Accrual
        $entry3 = JournalEntry::create([
            'entry_number' => 'JE-2024-003',
            'date' => Carbon::now()->subDay(),
            'description' => 'Staff salaries accrual for Jan',
            'status' => 'posted',
            'type' => 'payroll',
            'posted_at' => Carbon::now()->subDay(),
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entry3->id,
            'chart_of_account_id' => $salaryExpense,
            'debit' => 45000.00,
            'credit' => 0.00,
            'description' => 'January Salaries'
        ]);

        JournalEntryDetail::create([
            'journal_entry_id' => $entry3->id,
            'chart_of_account_id' => $ap, // Should be Salaries Payable really, but using AP based on seed
            'debit' => 0.00,
            'credit' => 45000.00,
            'description' => 'Salaries to pay'
        ]);
    }
}
