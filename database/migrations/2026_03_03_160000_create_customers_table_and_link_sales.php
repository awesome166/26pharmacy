<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('account_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->date('dob')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'phone']);
            $table->index(['account_id', 'email']);
            $table->index(['account_id', 'name']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->ulid('customer_id')->nullable()->after('user_id');
            $table->index('customer_id');

            // Keep nullable/no constraint for compatibility with existing data and SQLite behavior.
            // If your DB supports it cleanly, we can add FK in a follow-up migration.
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('customers');
    }
};
