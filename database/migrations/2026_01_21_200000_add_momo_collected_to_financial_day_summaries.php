<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('financial_day_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('financial_day_summaries', 'momo_collected')) {
                $table->decimal('momo_collected', 15, 2)->default(0)->after('card_collected');
            }
        });
    }

    public function down()
    {
        Schema::table('financial_day_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('financial_day_summaries', 'momo_collected')) {
                $table->dropColumn('momo_collected');
            }
        });
    }
};
