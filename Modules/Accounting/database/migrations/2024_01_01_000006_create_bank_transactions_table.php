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
        Schema::create('accounting_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name');
            $table->string('account_number');
            $table->string('bank_name');
            $table->string('currency')->default('USD');
            $table->string('swift_code')->nullable();

            // Link to GL account
            $table->foreignId('chart_of_account_id')->nullable()->constrained('accounting_chart_of_accounts')->nullOnDelete();

            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_date')->nullable();

            $table->decimal('current_balance', 15, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 2); // can be negative for withdrawal? or separate type
            $table->string('type'); // deposit, withdrawal
            $table->string('reference')->nullable();
            $table->text('description')->nullable();

            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();

            // Link to Journal Entry
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('accounting_bank_accounts')->cascadeOnDelete();
            $table->date('statement_date');
            $table->decimal('statement_balance', 15, 2);
            $table->decimal('book_balance', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->string('status')->default('draft'); // draft, reconciled
            $table->foreignId('reconciled_by')->nullable(); // User ID
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_bank_reconciliations');
        Schema::dropIfExists('accounting_bank_transactions');
        Schema::dropIfExists('accounting_bank_accounts');
    }
};
