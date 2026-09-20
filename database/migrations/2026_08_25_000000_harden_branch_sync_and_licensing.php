<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->ulid('branch_id')->nullable()->after('account_id')->index();
            $table->string('sync_token_hash', 64)->nullable()->unique()->after('trust_status');
            $table->timestamp('license_checked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });
        Schema::table('batches', function (Blueprint $table) {
            $table->ulid('account_id')->nullable()->index()->after('id');
            $table->ulid('branch_id')->nullable()->index()->after('account_id');
        });

        foreach (['event_ledger', 'sales', 'inventory', 'returns', 'financial_day_summaries'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->ulid('branch_id')->nullable()->index()->after('account_id');
            });
        }

        Schema::table('inventory', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'drug_id', 'batch_id', 'location']);
            $table->unique(['account_id', 'branch_id', 'drug_id', 'batch_id', 'location']);
        });
        Schema::table('financial_day_summaries', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'day']);
            $table->unique(['account_id', 'branch_id', 'day']);
        });

        Schema::table('event_ledger', function (Blueprint $table) {
            $table->string('previous_hash', 64)->nullable()->after('event_hash');
            $table->unique(['device_id', 'local_sequence']);
        });

        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->ulid('account_id');
            $table->ulid('branch_id')->nullable();
            $table->ulid('device_id');
            $table->unsignedBigInteger('last_global_sequence')->default(0);
            $table->timestamps();
            $table->unique(['account_id', 'branch_id', 'device_id']);
        });

        Schema::create('projected_events', function (Blueprint $table) {
            $table->ulid('event_id')->primary();
            $table->timestamp('projected_at');
        });

        Schema::create('sync_global_state', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_sequence')->default(0);
        });
        \Illuminate\Support\Facades\DB::table('sync_global_state')->insert([
            'id' => 1,
            'last_sequence' => (int) (\Illuminate\Support\Facades\DB::table('event_ledger')->max('global_sequence') ?? 0),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_global_state');
        Schema::dropIfExists('projected_events');
        Schema::dropIfExists('sync_cursors');

        Schema::table('event_ledger', function (Blueprint $table) {
            $table->dropUnique(['device_id', 'local_sequence']);
            $table->dropColumn('previous_hash');
        });

        Schema::table('inventory', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'branch_id', 'drug_id', 'batch_id', 'location']);
            $table->unique(['account_id', 'drug_id', 'batch_id', 'location']);
        });
        Schema::table('financial_day_summaries', function (Blueprint $table) {
            $table->dropUnique(['account_id', 'branch_id', 'day']);
            $table->unique(['account_id', 'day']);
        });

        foreach (['event_ledger', 'sales', 'inventory', 'returns', 'financial_day_summaries'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('branch_id'));
        }

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'sync_token_hash', 'license_checked_at', 'last_seen_at']);
        });
        Schema::table('batches', fn (Blueprint $table) => $table->dropColumn(['account_id', 'branch_id']));
    }
};
