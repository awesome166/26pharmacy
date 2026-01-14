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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('sale_id');
            $table->uuid('batch_id');
            $table->integer('quantity');
            $table->decimal('price', 15, 2); // Unit price at moment of sale
            $table->decimal('total', 15, 2); // Computed total (qty * price)
            $table->json('dosage_instructions')->nullable(); // Structured JSON
            $table->timestamps();

            $table->foreign('sale_id')->references('sale_id')->on('sales')->cascadeOnDelete();
            // batch_id can refer to batches table, but strictly speaking Inventory is the stock source.
            // Linking to batch_id for traceability.
            $table->foreign('batch_id')->references('batch_id')->on('batches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
