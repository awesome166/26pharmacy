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
        Schema::create('accounting_financial_reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->index(); // balance_sheet, income_statement, etc.
            $table->json('parameters')->nullable(); // date range, filters
            $table->json('data')->nullable(); // stored report data if needed
            $table->foreignId('generated_by')->nullable(); // User ID
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_financial_reports');
    }
};
