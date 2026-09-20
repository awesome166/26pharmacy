<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->ulid('branch_id')->primary();
                $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('address')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['account_id', 'is_active']);
                $table->unique(['account_id', 'code']);
            });
        }

        if (!Schema::hasColumn('drugs', 'is_prescription')) {
            Schema::table('drugs', function (Blueprint $table) {
                $table->boolean('is_prescription')->default(false)->after('storage_conditions');
            });
        }
        if (!Schema::hasColumn('drugs', 'is_controlled')) {
            Schema::table('drugs', function (Blueprint $table) {
                $table->boolean('is_controlled')->default(false)->after('is_prescription');
            });
        }
        if (!Schema::hasColumn('drugs', 'is_narcotic')) {
            Schema::table('drugs', function (Blueprint $table) {
                $table->boolean('is_narcotic')->default(false)->after('is_controlled');
            });
        }

        if (!Schema::hasColumn('event_ledger', 'sync_status')) {
            Schema::table('event_ledger', function (Blueprint $table) {
                // Add the column independently. SQLite can persist this part of
                // an ALTER operation even if a subsequent index creation fails.
                $table->string('sync_status', 20)->default('pending')->nullable()->after('event_hash');
            });
        }
        if (!Schema::hasIndex('event_ledger', 'event_ledger_sync_status_index')) {
            Schema::table('event_ledger', function (Blueprint $table) {
                $table->index('sync_status', 'event_ledger_sync_status_index');
            });
        }

        if (!Schema::hasColumn('batches', 'quantity_received')) {
            Schema::table('batches', function (Blueprint $table) {
                $table->integer('quantity_received')->default(0)->after('quantity');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');

        if (Schema::hasColumn('drugs', 'is_narcotic')) {
            Schema::table('drugs', function (Blueprint $table) {
                $table->dropColumn(['is_prescription', 'is_controlled', 'is_narcotic']);
            });
        }

        if (Schema::hasColumn('event_ledger', 'sync_status')) {
            Schema::table('event_ledger', function (Blueprint $table) {
                if (Schema::hasIndex('event_ledger', 'event_ledger_sync_status_index')) {
                    $table->dropIndex('event_ledger_sync_status_index');
                }
                $table->dropColumn('sync_status');
            });
        }

        if (Schema::hasColumn('batches', 'quantity_received')) {
            Schema::table('batches', function (Blueprint $table) {
                $table->dropColumn('quantity_received');
            });
        }
    }
};
