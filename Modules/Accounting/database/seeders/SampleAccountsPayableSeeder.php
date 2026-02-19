<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Accounting\Models\AccountsPayable;
use Modules\Accounting\Models\Payment;
use Carbon\Carbon;

class SampleAccountsPayableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pending Invoice
        AccountsPayable::create([
            'invoice_number' => 'INV-2024-001',
            'invoice_date' => Carbon::now()->subDays(15),
            'due_date' => Carbon::now()->addDays(15),
            'amount' => 2500.00,
            'amount_paid' => 0.00,
            'status' => 'pending',
            'description' => 'Purchase of MRI maintenance service',
        ]);

        // 2. Partially Paid Invoice
        $partial = AccountsPayable::create([
            'invoice_number' => 'INV-2024-002',
            'invoice_date' => Carbon::now()->subDays(20),
            'due_date' => Carbon::now()->subDays(5), // Overdue
            'amount' => 10000.00,
            'amount_paid' => 5000.00,
            'status' => 'partial',
            'description' => 'Pharmaceutical supplies batch A',
        ]);

        Payment::create([
            'payable_id' => $partial->id,
            'amount' => 5000.00,
            'payment_method' => 'bank_transfer',
            'reference' => 'TRX-998877',
            'payment_date' => Carbon::now()->subDays(10),
            'notes' => '50% advance payment',
        ]);

        // 3. Paid Invoice
        $paid = AccountsPayable::create([
            'invoice_number' => 'INV-2024-003',
            'invoice_date' => Carbon::now()->subMonth(),
            'due_date' => Carbon::now()->subWeeks(2),
            'amount' => 1500.00,
            'amount_paid' => 1500.00,
            'status' => 'paid',
            'description' => 'Office stationery',
        ]);

        Payment::create([
            'payable_id' => $paid->id,
            'amount' => 1500.00,
            'payment_method' => 'cash',
            'reference' => 'V-101',
            'payment_date' => Carbon::now()->subWeeks(3),
            'notes' => 'Petty cash payment',
        ]);
    }
}
