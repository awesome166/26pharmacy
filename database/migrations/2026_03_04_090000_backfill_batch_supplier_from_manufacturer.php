<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('batches')
            ->whereNull('supplier')
            ->whereNotNull('manufacturer')
            ->update([
                'supplier' => DB::raw('manufacturer'),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // No rollback needed for data backfill.
    }
};
