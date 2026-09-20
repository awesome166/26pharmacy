<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UploadAcknowledgements
{
    public function apply(string $accountId, string $deviceId, array $submittedIds, array $assignments): void
    {
        $submittedIds = array_values(array_unique($submittedIds));
        if (array_diff(array_keys($assignments), $submittedIds)) {
            throw new RuntimeException('UNEXPECTED_ACKNOWLEDGEMENT');
        }
        foreach ($assignments as $sequence) {
            if (filter_var($sequence, FILTER_VALIDATE_INT) === false || (int) $sequence < 1) {
                throw new RuntimeException('INVALID_SERVER_SEQUENCE');
            }
        }
        if (count(array_unique(array_map('intval', array_values($assignments)))) !== count($assignments)) {
            throw new RuntimeException('DUPLICATE_SERVER_SEQUENCE');
        }

        DB::transaction(function () use ($accountId, $deviceId, $submittedIds, $assignments): void {
            $rows = DB::table('event_ledger')->where('account_id', $accountId)
                ->where('device_id', $deviceId)->whereIn('id', $submittedIds)
                ->lockForUpdate()->get()->keyBy('id');
            if ($rows->count() !== count($submittedIds)) {
                throw new RuntimeException('OUTBOUND_BATCH_OWNERSHIP_CHANGED');
            }
            foreach ($submittedIds as $id) {
                $row = $rows->get($id);
                $query = DB::table('event_ledger')->where('id', $id)
                    ->where('account_id', $accountId)->where('device_id', $deviceId);
                if (array_key_exists($id, $assignments)) {
                    $sequence = (int) $assignments[$id];
                    if ($row->global_sequence !== null && (int) $row->global_sequence !== $sequence) {
                        throw new RuntimeException('ACKNOWLEDGEMENT_CHANGED');
                    }
                    $query->update(['global_sequence' => $sequence, 'sync_status' => 'synced', 'synced_at' => now()]);
                } elseif ($row->global_sequence === null) {
                    $query->update(['sync_status' => 'pending', 'synced_at' => null]);
                }
            }
        });
    }
}
