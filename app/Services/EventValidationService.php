<?php

namespace App\Services;

/**
 * Service for validating events against business rules and schema.
 */
class EventValidationService
{
    private const SUPPORTED_VERSIONS = [
        'SALE_FINALIZED' => [1], 'STOCK_ADJUSTED' => [1], 'SALE_RETURNED' => [1],
        'SALE_ITEM_DOSAGE_AMENDED' => [1], 'BATCH_REGISTERED' => [1],
        'STOCK_TRANSFERRED' => [1], 'DRUG_UPSERTED' => [1], 'DRUG_DELETED' => [1],
        'CUSTOMER_UPSERTED' => [1], 'CUSTOMER_DELETED' => [1], 'TAX_RATE_UPSERTED' => [1],
        'TAX_RATE_DELETED' => [1], 'BATCH_UPSERTED' => [1], 'BATCH_DELETED' => [1],
        'INVENTORY_UPSERTED' => [1], 'INVENTORY_DEACTIVATED' => [1],
        'SETTINGS_UPDATED' => [1], 'BRANCH_UPSERTED' => [1],
    ];

    /**
     * Reject unsupported protocol input before it reaches the immutable cloud
     * ledger. Projectors may still impose stricter aggregate checks.
     */
    public function validateForCloud(string $eventType, int $eventVersion, mixed $payload): array
    {
        $this->assertSupported($eventType, $eventVersion);
        if (!is_array($payload)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['events' => 'INVALID_EVENT_PAYLOAD']);
        }
        if ($eventType === 'CUSTOMER_UPSERTED') {
            \Illuminate\Support\Facades\Validator::make($payload, [
                'customer' => ['required', 'array:id,name,phone,email,dob'],
                'customer.id' => ['required', 'ulid'],
                'customer.name' => ['nullable', 'string', 'max:255'],
                'customer.phone' => ['nullable', 'string', 'max:50'],
                'customer.email' => ['nullable', 'email', 'max:255'],
                'customer.dob' => ['nullable', 'date_format:Y-m-d'],
            ])->validate();
        }
        if ($eventType === 'SALE_FINALIZED') {
            \Illuminate\Support\Facades\Validator::make($payload, [
                'sale_id' => ['required', 'ulid'], 'total_amount' => ['required', 'numeric', 'min:0'],
                'tax_amount' => ['nullable', 'numeric', 'min:0'], 'items' => ['nullable', 'array'],
                'items.*.id' => ['required', 'ulid'], 'items.*.inventory_id' => ['required', 'ulid'],
                'items.*.batch_id' => ['required', 'ulid'], 'items.*.drug_id' => ['required', 'ulid'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            ])->validate();
        }
        if ($eventType === 'SALE_ITEM_DOSAGE_AMENDED') {
            \Illuminate\Support\Facades\Validator::make($payload, [
                'sale_id' => ['required', 'ulid'], 'sale_item_id' => ['required', 'ulid'],
                'dosage_instructions' => ['present', 'nullable', 'array'],
            ])->validate();
        }

        return $payload;
    }

    public function assertSupported(string $eventType, int $eventVersion): void
    {
        if (!isset(self::SUPPORTED_VERSIONS[$eventType]) || !in_array($eventVersion, self::SUPPORTED_VERSIONS[$eventType], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['events' => 'UNSUPPORTED_EVENT_VERSION']);
        }
    }
    /**
     * Validate an event before it is committed to the ledger.
     *
     * @param string $eventType
     * @param array $payload
     * @return bool
     * @throws \Exception
     */
    public function validate(string $eventType, array $payload)
    {
        // Example: Ensure quantity is always positive for STOCK_ADJUSTED
        if ($eventType === 'STOCK_ADJUSTED' && ($payload['quantity'] ?? 0) <= 0) {
            throw new \Exception("Invalid stock adjustment quantity.");
        }
        return true;
    }

    /**
     * Check for sequence gaps in local events.
     *
     * @param string $accountid
     * @return bool
     */
    public function checkSequenceIntegrity(string $accountid)
    {
        $sequences = \Illuminate\Support\Facades\DB::table('event_ledger')
            ->where('account_id', $accountid)
            ->orderBy('local_sequence', 'asc')
            ->pluck('local_sequence')
            ->toArray();

        for ($i = 0; $i < count($sequences) - 1; $i++) {
            if ($sequences[$i + 1] !== $sequences[$i] + 1) {
                return false;
            }
        }

        return true;
    }
}
