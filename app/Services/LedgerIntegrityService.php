<?php

namespace App\Services;

/**
 * Service for periodic verification of ledger hashes to detect tampering.
 */
class LedgerIntegrityService
{
    /**
     * Verify the hash chain of the ledger for a specific branch.
     *
     * @param string $accountid
     * @return bool
     */
    public function verifyHashChain(string $accountid)
    {
        $events = \Illuminate\Support\Facades\DB::table('event_ledger')
            ->where('account_id', $accountid)
            ->orderBy('local_sequence', 'asc')
            ->get();

        $previousHash = str_repeat('0', 64);

        foreach ($events as $event) {
            $expectedHash = hash('sha256', $event->event_payload . $previousHash);
            if ($event->event_hash !== $expectedHash) {
                $this->reportTampering($accountid, "Hash mismatch at sequence {$event->local_sequence}");
                return false;
            }
            $previousHash = $event->event_hash;
        }

        return true;
    }

    /**
     * Report an integrity alert.
     *
     * @param string $accountid
     * @param string $message
     * @return void
     */
    public function reportTampering(string $accountid, string $message)
    {
        \Illuminate\Support\Facades\Log::critical("LEDGER TAMPERING DETECTED in branch {$accountid}: {$message}");
        // In reality, you'd send an SMS/Email to security or lock the branch
    }
}
