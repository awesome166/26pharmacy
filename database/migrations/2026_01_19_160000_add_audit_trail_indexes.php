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
        Schema::table('audit_trail', function (Blueprint $table) {
            // Composite indexes for dashboard performance
            $table->index(['actor_user_id', 'timestamp'], 'idx_audit_actor_time');
            $table->index(['entity_type', 'entity_id', 'timestamp'], 'idx_audit_entity_time');
            $table->index(['account_id', 'action', 'timestamp'], 'idx_audit_account_action_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_trail', function (Blueprint $table) {
            $table->dropIndex('idx_audit_actor_time');
            $table->dropIndex('idx_audit_entity_time');
            $table->dropIndex('idx_audit_account_action_time');
        });
    }
};
