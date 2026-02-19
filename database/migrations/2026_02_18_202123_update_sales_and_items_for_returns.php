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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('total_returned_amount', 10, 2)->default(0)->after('total_amount');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->boolean('is_returned')->default(false)->after('line_total');
            $table->integer('return_quantity')->default(0)->after('is_returned');
            $table->timestamp('return_date')->nullable()->after('return_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('total_returned_amount');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['is_returned', 'return_quantity', 'return_date']);
        });
    }
};
