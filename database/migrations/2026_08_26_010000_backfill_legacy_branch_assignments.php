<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Older batches predate account_id. Infer their tenant from stock
            // first and fall back to the owning drug catalog row.
            DB::table('batches')->whereNull('account_id')->orderBy('id')->each(function ($batch) {
                $accountId = DB::table('inventory')->where('batch_id', $batch->id)->value('account_id')
                    ?: DB::table('drugs')->where('id', $batch->drug_id)->value('account_id');
                if ($accountId) {
                    DB::table('batches')->where('id', $batch->id)->update(['account_id' => $accountId]);
                }
            });

            foreach (DB::table('accounts')->orderBy('id')->get(['id', 'name']) as $account) {
                $branchId = DB::table('branches')->where('account_id', $account->id)
                    ->orderByDesc('is_active')->orderBy('created_at')->value('branch_id');

                if (!$branchId) {
                    $branchId = (string) Str::ulid();
                    DB::table('branches')->insert([
                        'branch_id' => $branchId,
                        'account_id' => $account->id,
                        'name' => 'Main Branch',
                        'code' => 'MAIN',
                        'tax_jurisdiction' => 'Default',
                        'timezone' => config('app.timezone', 'UTC'),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach (['devices', 'batches', 'event_ledger', 'sales', 'inventory', 'returns', 'financial_day_summaries', 'sync_cursors'] as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                        DB::table($table)->where('account_id', $account->id)->whereNull('branch_id')
                            ->update(['branch_id' => $branchId]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        // Intentionally retain branch assignments: removing them after the app
        // has accepted branch-scoped transactions would destroy ownership data.
    }
};
