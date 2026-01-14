<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_ledger', function (Blueprint $table) {
            $table->bigInteger('global_sequence')->nullable()->index()->after('local_sequence');
            $table->timestamp('synced_at')->nullable()->after('event_hash');
        });
    }

    public function down(): void
    {
        Schema::table('event_ledger', function (Blueprint $table) {
            $table->dropColumn(['global_sequence', 'synced_at']);
        });
    }
};
