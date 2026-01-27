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
        Schema::create('returns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('sale_id')->constrained('sales');
            $table->ulid('account_id')->index(); // Tenant Scoping
            $table->foreignUlid('user_id')->nullable()->constrained('users'); // Who processed it

            $table->decimal('refund_amount', 10, 2);
            $table->string('refund_method')->nullable();
            $table->string('reason')->nullable();

            $table->timestamp('returned_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignUlid('sale_item_id')->constrained('sale_items');

            $table->integer('quantity');
            $table->decimal('refund_amount', 10, 2);
            $table->boolean('is_restocked')->default(false);
            $table->string('condition')->nullable(); // Good, Damaged, Expired

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
