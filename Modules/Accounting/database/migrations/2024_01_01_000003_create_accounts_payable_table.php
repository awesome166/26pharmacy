<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounting_accounts_payables', function (Blueprint $table) {
            $table->id();
            // Vendor/Supplier reference (polymorphic or direct ID if vendors table exists)
            // Assuming we link to a Contact or User model, using morphs for flexibility
            $table->nullableMorphs('vendor');

            $table->string('invoice_number')->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->index();
            $table->decimal('amount', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->string('status')->default('pending')->index(); // pending, partial, paid, cancelled
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->nullable()->constrained('accounting_accounts_payables')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method'); // cash, bank_transfer, cheque, credit_card
            $table->string('reference')->nullable(); // cheque number, transaction ID
            $table->date('payment_date');
            $table->text('notes')->nullable();

            // Link to the journal entry created for this payment
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_payments');
        Schema::dropIfExists('accounting_accounts_payables');
    }
};
