<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_stream_heads', function (Blueprint $table): void {
            $table->ulid('device_id')->primary();
            $table->unsignedBigInteger('last_local_sequence')->default(0);
            $table->string('last_event_hash', 64)->default(str_repeat('0', 64));
            $table->timestamps();
            $table->foreign('device_id')->references('device_id')->on('devices');
        });

        DB::table('event_ledger')->select('device_id', DB::raw('MAX(local_sequence) as last_sequence'))
            ->groupBy('device_id')->orderBy('device_id')->each(function (object $stream): void {
                $event = DB::table('event_ledger')->where('device_id', $stream->device_id)
                    ->where('local_sequence', $stream->last_sequence)->first();
                if ($event) {
                    DB::table('device_stream_heads')->insert([
                        'device_id' => $stream->device_id,
                        'last_local_sequence' => $stream->last_sequence,
                        'last_event_hash' => $event->event_hash,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void { Schema::dropIfExists('device_stream_heads'); }
};
