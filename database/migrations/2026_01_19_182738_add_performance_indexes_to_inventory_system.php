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
        // Inventory table indexes
        Schema::table('inventory', function (Blueprint $table) {
            // Composite index for active stock filtering (used in POS)
            $table->index(['is_active', 'quantity_on_hand'], 'idx_inventory_active_stock');

            // Composite index for tenant-aware queries
            $table->index(['account_id', 'is_active'], 'idx_inventory_account_active');

            // Composite index for relationship lookups
            $table->index(['drug_id', 'batch_id'], 'idx_inventory_drug_batch');
        });

        // Drug table indexes
        Schema::table('drugs', function (Blueprint $table) {
            // Single column index for name searches
            $table->index('name', 'idx_drug_name');

            // Index for generic name searches
            $table->index('generic_name', 'idx_drug_generic_name');
        });

        // Batch table indexes
        Schema::table('batches', function (Blueprint $table) {
            // Index for expiry date queries (important for pharmacy compliance)
            $table->index('expiry_date', 'idx_batch_expiry');

            // Index for active batch filtering
            $table->index('is_active', 'idx_batch_active');
        });

        // Sales table indexes for reporting
        Schema::table('sales', function (Blueprint $table) {
            // Index for date-based queries
            $table->index('finalized_at', 'idx_sales_finalized_at');

            // Composite index for account-based reporting
            $table->index(['account_id', 'finalized_at'], 'idx_sales_account_date');
        });

        // Sale items table for join optimization
        Schema::table('sale_items', function (Blueprint $table) {
            // Index for sale lookups
            $table->index('sale_id', 'idx_sale_items_sale_id');

            // Index for inventory tracking
            $table->index('inventory_id', 'idx_sale_items_inventory_id');

            // Index for drug-based analytics
            $table->index('drug_id', 'idx_sale_items_drug_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropIndex('idx_inventory_active_stock');
            $table->dropIndex('idx_inventory_account_active');
            $table->dropIndex('idx_inventory_drug_batch');
        });

        Schema::table('drugs', function (Blueprint $table) {
            $table->dropIndex('idx_drug_name');
            $table->dropIndex('idx_drug_generic_name');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex('idx_batch_expiry');
            $table->dropIndex('idx_batch_active');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_finalized_at');
            $table->dropIndex('idx_sales_account_date');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex('idx_sale_items_sale_id');
            $table->dropIndex('idx_sale_items_inventory_id');
            $table->dropIndex('idx_sale_items_drug_id');
        });
    }
};
