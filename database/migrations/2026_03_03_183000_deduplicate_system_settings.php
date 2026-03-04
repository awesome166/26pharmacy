<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('system_settings')
            ->select('key', 'account_id', DB::raw('COUNT(*) as c'))
            ->groupBy('key', 'account_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $baseQuery = DB::table('system_settings')->where('key', $dup->key);
            if ($dup->account_id === null) {
                $baseQuery->whereNull('account_id');
            } else {
                $baseQuery->where('account_id', $dup->account_id);
            }

            $winner = (clone $baseQuery)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->first();

            if (!$winner) {
                continue;
            }

            $baseQuery->delete();

            DB::table('system_settings')->insert([
                'key' => $winner->key,
                'account_id' => $winner->account_id,
                'type' => $winner->type ?? ($winner->account_id ? 'tenant' : 'platform'),
                'value' => $winner->value,
                'created_at' => $winner->created_at,
                'updated_at' => $winner->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible data cleanup
    }
};
