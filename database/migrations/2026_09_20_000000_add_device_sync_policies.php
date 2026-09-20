<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sync_policies', function (Blueprint $table): void {
            $table->ulid('device_id')->primary();
            $table->ulid('account_id')->index();
            $table->string('download_mode', 20)->default('disabled');
            $table->unsignedBigInteger('revision')->default(1);
            $table->json('allowed_branch_ids');
            $table->json('allowed_datasets');
            $table->ulid('changed_by')->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamps();
            $table->foreign('device_id')->references('device_id')->on('devices');
            $table->foreign('account_id')->references('id')->on('accounts');
        });
    }

    public function down(): void { Schema::dropIfExists('device_sync_policies'); }
};
