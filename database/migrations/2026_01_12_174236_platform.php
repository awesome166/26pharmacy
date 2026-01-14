<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------
        // Identity & Tenant Tables
        // ----------------------------

        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('tenant_id')->primary();
            $table->string('legal_name');
            $table->string('tax_identifier')->unique();
            $table->json('regulatory_metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('branch_id')->primary();
            $table->uuid('tenant_id');
            $table->string('branch_name');
            $table->string('physical_address')->nullable();
            $table->string('license_number')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('device_id')->primary();
            $table->uuid('branch_id');
            $table->string('device_name')->nullable();
            $table->string('trust_status')->default('active'); // active / revoked
            $table->timestamps();

            $table->foreign('branch_id')->references('branch_id')->on('branches')->cascadeOnDelete();
        });


        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('role_id')->primary();
            $table->string('role_name');
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        // ----------------------------
        // Pivot Tables (Many-to-Many)
        // ----------------------------

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('user_id'); // Using users.user_id (UUID) not users.id (BigInt) if intended?
            // Wait, users table has mixed IDs.
            // 0001 migration has $table->id('id') AND $table->uuid('user_id').
            // Let's stick to UUID for the pivot to match other FKs.
            $table->uuid('role_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('tenant_id')->on('tenants')->cascadeOnDelete();
            // We can't easily FK to users.user_id if it's not unique/indexed in migration?
            // users.user_id IS nullable in 0001 migration.
            // Let's assume we use the UUID for relationships.
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('branch_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->foreign('branch_id')->references('branch_id')->on('branches')->cascadeOnDelete();
        });

        // ----------------------------
        // Event Sourced Ledger
        // ----------------------------

        Schema::create('event_ledger', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('device_id');
            $table->uuid('actor_user_id')->nullable();
            $table->string('event_type');
            $table->integer('event_version')->default(1);
            $table->json('event_payload');
            $table->bigInteger('local_sequence');
            $table->timestamp('event_time_utc');
            $table->string('event_hash', 64);
            $table->timestamp('received_at_cloud')->nullable();

            $table->index(['tenant_id']);
            $table->index(['branch_id']);
            $table->index(['event_time_utc']);
        });

        // ----------------------------
        // Domain Read Models (Derived)
        // ----------------------------

        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('sale_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->string('payment_type')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id']);
        });

        Schema::create('inventory', function (Blueprint $table) {
            $table->uuid('inventory_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('drug_id');
            $table->uuid('batch_id');
            $table->decimal('selling_price', 15, 2)->default(0);

            $table->integer('quantity_on_hand')->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'drug_id']);
        });

        Schema::create('financial_day_summaries', function (Blueprint $table) {
            $table->uuid('summary_id')->primary();
            $table->uuid('branch_id');
            $table->date('day');
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('tax_collected', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'day']);
        });

        // ----------------------------
        // Catalog & Reference Data
        // ----------------------------

        Schema::create('drugs', function (Blueprint $table) {
            $table->uuid('drug_id')->primary();
            $table->string('name');
            $table->string('strength')->nullable();
            $table->string('regulatory_code')->nullable();
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->uuid('batch_id')->primary();
            $table->uuid('drug_id');
            $table->date('expiry_date')->nullable();
            $table->string('lot_number')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->string('name')->nullable();
            $table->string('manufacturer')->nullable();
            $table->timestamps();

            $table->foreign('drug_id')->references('drug_id')->on('drugs')->cascadeOnDelete();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->uuid('tax_rate_id')->primary();
            $table->string('jurisdiction');
            $table->decimal('percentage', 5, 2);
            $table->date('effective_from');
            $table->timestamps();
        });

        // ----------------------------
        // Audit & Compliance
        // ----------------------------

        Schema::create('audit_trail', function (Blueprint $table) {
            $table->uuid('audit_id')->primary();
            $table->string('entity_type');
            $table->uuid('entity_id');
            $table->string('action');
            $table->uuid('actor_user_id')->nullable();
            $table->timestamp('timestamp');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('event_rejections', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('rejection_reason');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rejections');
        Schema::dropIfExists('audit_trail');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('drugs');
        Schema::dropIfExists('financial_day_summaries');
        Schema::dropIfExists('inventory');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('event_ledger');
        Schema::dropIfExists('roles');
        // Schema::dropIfExists('users');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
    }
};
