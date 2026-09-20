<?php

namespace App\Services\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Checks that IDs embedded in a device event cannot point at another tenant's
 * known records. Unknown IDs are allowed so an event can create its aggregate;
 * projectors still enforce the final ownership check under their transaction.
 */
final class EventPayloadAuthorization
{
    public function authorize(string $accountId, string $branchId, string $type, array $payload): void
    {
        $checks = match ($type) {
            'SALE_FINALIZED' => [
                ['sales', $payload['sale_id'] ?? $payload['id'] ?? null, false],
                ['customers', data_get($payload, 'customer.id'), false],
            ],
            'CUSTOMER_UPSERTED' => [['customers', data_get($payload, 'customer.id'), false]],
            'CUSTOMER_DELETED' => [['customers', $payload['id'] ?? null, false]],
            'DRUG_UPSERTED' => [['drugs', data_get($payload, 'drug.id'), false]],
            'DRUG_DELETED' => [['drugs', $payload['id'] ?? null, false]],
            'BATCH_UPSERTED' => [['batches', data_get($payload, 'batch.id'), true]],
            'BATCH_DELETED' => [['batches', $payload['id'] ?? null, true]],
            'BATCH_REGISTERED' => [['batches', $payload['batch_id'] ?? null, true]],
            'INVENTORY_UPSERTED' => [['inventory', data_get($payload, 'inventory.id'), true]],
            'INVENTORY_DEACTIVATED' => [['inventory', $payload['id'] ?? null, true]],
            'STOCK_ADJUSTED' => [['inventory', $payload['inventory_id'] ?? null, true], ['batches', $payload['batch_id'] ?? null, true]],
            'TAX_RATE_UPSERTED' => [['tax_rates', data_get($payload, 'tax_rate.id'), false]],
            'TAX_RATE_DELETED' => [['tax_rates', $payload['id'] ?? null, false]],
            'BRANCH_UPSERTED' => [['branches', data_get($payload, 'branch.branch_id'), true]],
            default => [],
        };

        foreach ($checks as [$table, $id, $requiresBranch]) {
            $this->assertOwned($table, $id, $accountId, $requiresBranch ? $branchId : null);
        }
        foreach ($payload['items'] ?? [] as $item) {
            if (!is_array($item)) continue;
            $this->assertOwned('inventory', $item['inventory_id'] ?? null, $accountId, $branchId);
            $this->assertOwned('batches', $item['batch_id'] ?? null, $accountId, $branchId);
            $this->assertOwned('drugs', $item['drug_id'] ?? null, $accountId);
        }
    }

    private function assertOwned(string $table, mixed $id, string $accountId, ?string $branchId = null): void
    {
        if (!is_string($id) || $id === '') return;
        // Branch primary keys use branch_id rather than id.
        $record = $table === 'branches'
            ? DB::table($table)->where('branch_id', $id)->first()
            : DB::table($table)->where('id', $id)->first();
        if (!$record) return;
        if ((string) $record->account_id !== $accountId
            || ($branchId !== null && isset($record->branch_id) && (string) $record->branch_id !== $branchId)) {
            throw ValidationException::withMessages(['events' => 'ENTITY_OWNERSHIP_CONFLICT']);
        }
    }
}
