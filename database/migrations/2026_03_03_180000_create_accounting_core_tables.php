<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('account_id')->index();
            $table->string('code');
            $table->string('name');
            $table->enum('type', ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense']);
            $table->ulid('parent_id')->nullable()->index();
            $table->text('description')->nullable();
            $table->boolean('is_group')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['account_id', 'code']);
            $table->index(['account_id', 'type']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('account_id')->index();
            $table->string('entry_number')->index();
            $table->date('date')->index();
            $table->string('status')->default('draft')->index();
            $table->string('description');
            $table->string('reference')->nullable()->index();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->ulid('posted_by_user_id')->nullable()->index();
            $table->timestamp('voided_at')->nullable();
            $table->ulid('voided_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['account_id', 'entry_number']);
            $table->index(['account_id', 'date', 'status']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('journal_entry_id')->index();
            $table->ulid('chart_of_account_id')->index();
            $table->decimal('debit', 15, 2)->default(0);
            $table->decimal('credit', 15, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->index(['chart_of_account_id', 'reference_type', 'reference_id']);
        });

        Schema::create('daily_book_closures', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('account_id')->index();
            $table->date('business_date')->index();
            $table->string('status')->default('closed')->index();
            $table->timestamp('closed_at')->nullable();
            $table->ulid('closed_by_user_id')->nullable()->index();
            $table->json('summary')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_book_closures');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
    }
};
