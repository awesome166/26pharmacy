<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('batches', 'quantity_received') && Schema::hasColumn('batches', 'quantity_recieved')) {
            DB::table('batches')
                ->where('quantity_received', 0)
                ->update(['quantity_received' => DB::raw('quantity_recieved')]);
        }

        Schema::table('branches', function (Blueprint $table) {
            $table->string('tax_jurisdiction')->default('Default')->after('code');
            $table->string('timezone')->default('UTC')->after('tax_jurisdiction');
        });

        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'jurisdiction', 'tax_name', 'effective_from']);
            $table->decimal('minimum_taxable_amount', 15, 2)->nullable()->after('percentage');
            $table->decimal('maximum_taxable_amount', 15, 2)->nullable()->after('minimum_taxable_amount');
            $table->unsignedSmallInteger('calculation_order')->default(0)->after('maximum_taxable_amount');
            $table->boolean('is_compound')->default(false)->after('calculation_order');
            $table->index(['account_id', 'jurisdiction', 'tax_name'], 'tax_rate_lookup_index');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->json('tax_breakdown')->nullable()->after('tax_amount');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->default(0)->after('price');
            $table->json('tax_breakdown')->nullable()->after('tax_amount');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unique(['account_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', fn (Blueprint $table) => $table->dropUnique(['account_id', 'reference']));
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn(['unit_cost', 'tax_breakdown']));
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('tax_breakdown'));
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropIndex('tax_rate_lookup_index');
            $table->dropColumn(['minimum_taxable_amount', 'maximum_taxable_amount', 'calculation_order', 'is_compound']);
            $table->unique(['account_id', 'jurisdiction', 'tax_name', 'effective_from']);
        });
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn(['tax_jurisdiction', 'timezone']));
    }
};
