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
        Schema::create('accounting_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('fiscal_year'); // 2024
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('draft'); // draft, approved, active, closed
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable(); // User ID
            $table->foreignId('approved_by')->nullable(); // User ID
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('accounting_budget_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('accounting_budgets')->cascadeOnDelete();
            $table->foreignId('cost_center_id')->nullable()->constrained('accounting_cost_centers')->nullOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('accounting_chart_of_accounts')->cascadeOnDelete();

            $table->string('period'); // annual, Q1, Q2, Jan, Feb...
            $table->decimal('amount', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['budget_id', 'cost_center_id', 'chart_of_account_id', 'period'], 'budget_detail_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_budget_details');
        Schema::dropIfExists('accounting_budgets');
    }
};
