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
        Schema::create('accounting_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->morphs('approvable'); // journal_entry, payment, budget
            $table->foreignId('requested_by')->nullable(); // User ID
            $table->foreignId('approved_by')->nullable(); // User ID
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('comments')->nullable();
            $table->integer('level')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_approval_requests');
    }
};
