<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix existing records in SQLite where 'day' might have time component
        if (DB::getDriverName() === 'sqlite') {
            // 1. Deduplicate: Identify rows that will conflict after truncation and delete the older/less relevant ones.
            // We want to keep the one with the specific DATE format if it exists, or the latest updated one.

            // This query is a bit complex for raw delete with complex joins, easiest is to iterate or use a temp table approach,
            // but for a small dataset, a clever DELETE works.
            // We keep the row with the MAX updated_at for each (account_id, stripped_day) group.

            DB::statement("
                DELETE FROM financial_day_summaries
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id, MAX(updated_at)
                        FROM financial_day_summaries
                        GROUP BY account_id, STRFTIME('%Y-%m-%d', day)
                    )
                )
            ");

            // 2. Now safe to Update
            DB::statement("UPDATE financial_day_summaries SET day = STRFTIME('%Y-%m-%d', day) WHERE length(day) > 10");

        } elseif (DB::getDriverName() === 'mysql') {
             // Similar logic for MySQL
             // Delete duplicates keeping latest
             DB::statement("
                DELETE t1 FROM financial_day_summaries t1
                INNER JOIN financial_day_summaries t2
                WHERE t1.id < t2.id
                AND t1.account_id = t2.account_id
                AND DATE(t1.day) = DATE(t2.day)
             ");

             DB::statement("UPDATE financial_day_summaries SET day = DATE_FORMAT(day, '%Y-%m-%d')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse action needed for data cleanup
    }
};
