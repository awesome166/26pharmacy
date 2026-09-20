<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerIntegrityService
{
    public function __construct(private readonly EventLedgerService $ledger) {}

    public function verifyHashChain(string $accountId): bool
    {
        $deviceIds = DB::table('event_ledger')->where('account_id', $accountId)
            ->distinct()->pluck('device_id');

        foreach ($deviceIds as $deviceId) {
            $events = DB::table('event_ledger')->where('account_id', $accountId)
                ->where('device_id', $deviceId)->orderBy('local_sequence')->get();
            $previousHash = str_repeat('0', 64);
            $expectedSequence = 1;

            foreach ($events as $event) {
                if ((int) $event->local_sequence !== $expectedSequence
                    || !hash_equals($previousHash, (string) $event->previous_hash)) {
                    $this->reportTampering($accountId, "Broken chain continuity on device {$deviceId} at sequence {$event->local_sequence}");
                    return false;
                }

                $payload = is_string($event->event_payload)
                    ? json_decode($event->event_payload, true, flags: JSON_THROW_ON_ERROR)
                    : $event->event_payload;
                $expectedHash = $this->ledger->computeEventHash([
                    'id' => (string) $event->id,
                    'account_id' => (string) $event->account_id,
                    'branch_id' => (string) $event->branch_id,
                    'device_id' => (string) $event->device_id,
                    'actor_user_id' => $event->actor_user_id,
                    'event_type' => $event->event_type,
                    'event_version' => (int) ($event->event_version ?? 1),
                    'event_payload' => $payload,
                    'local_sequence' => (int) $event->local_sequence,
                    'event_time_utc' => CarbonImmutable::parse($event->event_time_utc)->toISOString(),
                    'previous_hash' => $previousHash,
                ]);

                if (!hash_equals($expectedHash, (string) $event->event_hash)) {
                    $this->reportTampering($accountId, "Hash mismatch on device {$deviceId} at sequence {$event->local_sequence}");
                    return false;
                }

                $previousHash = (string) $event->event_hash;
                $expectedSequence++;
            }
        }

        return true;
    }

    public function reportTampering(string $accountId, string $message): void
    {
        Log::critical("LEDGER TAMPERING DETECTED for account {$accountId}: {$message}");
    }
}
