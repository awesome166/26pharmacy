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
        Schema::create('accounting_chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index();
            $table->string('name');
            $table->string('type')->index(); // Asset, Liability, Equity, Revenue, Expense
            $table->string('category')->nullable()->index(); // Current Asset, etc.
            $table->foreignId('parent_id')->nullable()->constrained('accounting_chart_of_accounts')->nullOnDelete();
            $table->boolean('is_group')->default(false); // If true, cannot have transactions, only children
            $table->boolean('is_bank')->default(false);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_chart_of_accounts');
    }
};
