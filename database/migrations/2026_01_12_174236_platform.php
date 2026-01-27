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

        // Devices table (without branch reference)
        Schema::create('devices', function (Blueprint $table) {
            $table->ulid('device_id')->primary();
            $table->string('device_name')->nullable();
            $table->string('trust_status')->default('active'); // active / revoked
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('device_type')->nullable()->comment('POS, Mobile, Tablet, Kiosk');
            $table->string('serial_number')->nullable()->unique();
            $table->string('mac_address')->nullable()->unique();
            $table->timestamps();
                 $table->index(['account_id']);
            $table->index(['trust_status']);
        });

        // ----------------------------
        // Event Sourced Ledger
        // ----------------------------

        Schema::create('event_ledger', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('account_id');
            $table->ulid('device_id');
            $table->ulid('actor_user_id')->nullable();
            $table->string('event_type');
            $table->string('event_category')->nullable()->comment('sale, inventory, prescription, audit');
            $table->integer('event_version')->default(1);
            $table->json('event_payload');
            $table->bigInteger('local_sequence');
            $table->timestamp('event_time_utc');
            $table->string('event_hash', 64);
            $table->timestamp('received_at_cloud')->nullable();
            $table->json('metadata')->nullable()->comment('Additional event metadata');
            $table->timestamps();


            $table->index(['account_id', 'device_id']);
            $table->index(['event_time_utc']);
            $table->index(['sync_status']);
            $table->index(['event_category']);
            $table->index(['account_id', 'local_sequence']);
            $table->foreign('device_id')->references('device_id')->on('devices');
        });

        // ----------------------------
        // Domain Read Models (Derived)
        // ----------------------------

        Schema::create('sales', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->integer('account_id');
            $table->ulid('user_id')->nullable(); // who served the sale

            $table->string('customer_name')->nullable();
            $table->date('customer_dob')->nullable()->comment('Date of birth for age verification');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();

            $table->decimal('subtotal_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->string('payment_type')->nullable()->comment('cash, card, insurance, momo');
            $table->json('payment_metadata')->nullable()->comment('Additional sale metadata');


            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['account_id']);
            $table->index(['finalized_at']);
            $table->index(['account_id', 'finalized_at']);
        });

        // ----------------------------
        // Pharmacy Specific Tables
        // ----------------------------

        Schema::create('sale_items', function (Blueprint $table) {
            // $table->id();
            $table->ulid('id')->primary();
            $table->ulid('batch_id');
            $table->ulid('inventory_id');
            $table->ulid('sale_id');
            $table->ulid('drug_id');
            $table->integer('quantity');
            $table->decimal('price', 15, 2); // Unit price at moment of sale
            $table->decimal('line_total', 15, 2); // Computed total (qty * price)
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->boolean('requires_prescription')->default(false);
            $table->json('prescription_metadata')->nullable();


            $table->json('dosage_instructions')->nullable(); // Structured JSON
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            // batch_id can refer to batches table, but strictly speaking Inventory is the stock source.
            // Linking to batch_id for traceability.
            // $table->foreign('batch_id')->references('batch_id')->on('batches');
             $table->foreign('inventory_id')->references('id')->on('inventory')->restrictOnDelete();
            $table->foreign('drug_id')->references('id')->on('drugs')->nullOnDelete();
            $table->foreign('batch_id')->references('id')->on('batches')->nullOnDelete();

            $table->index(['sale_id']);
            $table->index(['inventory_id']);
            $table->index(['drug_id']);

        });




        Schema::create('inventory', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('account_id');
            $table->ulid('drug_id');
            $table->ulid('batch_id');
            $table->decimal('selling_price', 15, 2)->default(0);
            $table->decimal('cost_price', 15, 2)->default(0)->comment('Current cost from batch');
            $table->integer('reorder_level')->default(0);
            $table->string('location')->nullable()->comment('Shelf location in pharmacy');
            $table->boolean('is_active')->default(true);

            $table->integer('quantity_on_hand')->default(0);
            $table->timestamps();



            $table->foreign('drug_id')->references('id')->on('drugs')->restrictOnDelete();
            $table->foreign('batch_id')->references('id')->on('batches')->restrictOnDelete();

            $table->index(['account_id', 'drug_id']);
            $table->index(['account_id', 'quantity_on_hand']);
            $table->index(['drug_id', 'batch_id']);
            $table->index(['is_active']);
            $table->unique(['account_id', 'drug_id', 'batch_id', 'location']);

        });

    Schema::create('financial_day_summaries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('account_id');
            $table->date('day');
            $table->decimal('gross_sales', 15, 2)->default(0);
            $table->decimal('net_sales', 15, 2)->default(0);
            $table->decimal('tax_collected', 15, 2)->default(0);
            $table->decimal('total_discounts', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0)->comment('Cost of goods sold');
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->integer('total_transactions')->default(0);
            $table->integer('prescription_count')->default(0);
            $table->integer('otc_count')->default(0)->comment('Over-the-counter items');
            $table->decimal('cash_collected', 15, 2)->default(0);
            $table->decimal('card_collected', 15, 2)->default(0);
            $table->decimal('insurance_billed', 15, 2)->default(0);
            $table->decimal('returns_amount', 15, 2)->default(0);
            $table->integer('customer_count')->default(0);
            $table->json('payment_breakdown')->nullable();
            $table->json('category_breakdown')->nullable()->comment('Sales by drug category');
            $table->boolean('is_closed')->default(false)->comment('Day closed for reconciliation');
            $table->timestamp('closed_at')->nullable();
            $table->ulid('closed_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'day']);
            $table->index(['account_id', 'day', 'is_closed']);
            $table->index(['day']);
        });

        // ----------------------------
        // Catalog & Reference Data
        // ----------------------------

   Schema::create('drugs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('strength')->nullable();
            $table->string('form')->nullable()->comment('Tablet, Capsule, Liquid, Cream, etc.');
            $table->string('route')->nullable()->comment('Oral, Topical, Inhalation, etc.');
            $table->string('regulatory_code')->nullable()->comment('NDC, DIN, etc.');
            $table->string('manufacturer')->nullable();
            $table->string('supplier')->nullable();
            $table->string('drug_class')->nullable()->comment('Therapeutic class');
            $table->string('storage_conditions')->nullable()->comment('Room temp, Refrigerate, etc.');
            $table->text('description')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('contraindications')->nullable();
            $table->string('schedule')->nullable()->comment('Drug schedule/class');
            $table->json('alternate_names')->nullable()->comment('Brand names, aliases');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['name']);
            $table->index(['generic_name']);
            $table->index(['regulatory_code']);
            $table->index(['is_prescription']);
            $table->index(['is_controlled']);
            $table->index(['drug_class']);
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('drug_id');
            $table->date('manufacture_date')->nullable();

            $table->string('manufacturer')->nullable();
            $table->string('supplier')->nullable();
            $table->date('received_date')->nullable();

            $table->boolean('is_active')->default(true);

            $table->date('expiry_date')->nullable();
            $table->string('lot_number')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('quantity_recieved')->default(0);
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->string('name')->nullable();
            $table->string('storage_location')->nullable();
            $table->timestamps();

            $table->foreign('drug_id')->references('id')->on('drugs')->cascadeOnDelete();
            $table->index(['drug_id']);
            $table->index(['expiry_date']);
            $table->index(['is_active']);
            $table->index(['manufacturer']);
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('account_id')->nullable()->comment('Null for global rates');
            $table->string('jurisdiction');
            $table->string('tax_name');
            $table->decimal('percentage', 5, 2);
            $table->string('tax_type')->default('sales')->comment('sales, pharmacy, special, service');
            $table->json('applicable_categories')->nullable()->comment('Drug categories this applies to');
            $table->text('description')->nullable();
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);

            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['jurisdiction']);
            $table->index(['account_id', 'is_active']);
            $table->index(['effective_from', 'effective_to']);
            $table->unique(['account_id', 'jurisdiction', 'tax_name', 'effective_from']);
        });

        // ----------------------------
        // Audit & Compliance
        // ----------------------------

        Schema::create('audit_trail', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->string('action');
            $table->ulid('actor_user_id')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->timestamp('timestamp');
            $table->json('metadata')->nullable();
            $table->timestamps();

                $table->index(['account_id', 'entity_type', 'entity_id']);
            $table->index(['actor_user_id']);
            $table->index(['timestamp']);
            $table->index(['entity_type', 'action']);
            $table->index(['account_id', 'timestamp']);
        });




        Schema::create('event_rejections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('rejection_reason');
            $table->ulid('reviewed_by')->nullable();
            $table->text('rejection_details')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->string('resolution_action')->nullable()->comment('corrected, ignored, resubmitted');
            $table->ulid('resolved_event_id')->nullable()->comment('Corrected event ID');
            $table->json('correction_data')->nullable();
            $table->timestamps();

            $table->index(['resolved_at']);
            $table->index(['reviewed_by']);
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
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('event_ledger');
        Schema::dropIfExists('devices');
    }
};
