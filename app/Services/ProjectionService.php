<?php

namespace App\Services;

/**
 * Service for managing read-model projections from the event ledger.
 */
class ProjectionService
{
    /**
     * Project a single event into the read models.
     *
     * Note: Explicitly sets account_id from event payload since projections
     * rebuild historical data and may not have current TenantContext.
     *
     * @param object $event
     * @return void
     */
    public function projectEvent($event)
    {
        $payload = is_string($event->event_payload) ? json_decode($event->event_payload, true) : $event->event_payload;
        // Envelope ownership is immutable. Never let a payload select its tenant
        // or branch while replaying it.
        $accountId = $event->account_id ?? null;
        $branchId = $event->branch_id ?? null;
        $eventTime = $event->event_time_utc ?? $payload['event_time_utc'] ?? now();

        if (!$accountId) {
            \Illuminate\Support\Facades\Log::warning('Projection skipped: no account_id', [
                'event_type' => $event->event_type,
                'event_id' => $event->id ?? null,
            ]);
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($event, $payload, $accountId, $branchId, $eventTime) {
            if (\Illuminate\Support\Facades\DB::table('projected_events')->where('event_id', $event->id)->exists()) {
                return;
            }

            switch ($event->event_type) {
            case 'SALE_FINALIZED': {
                $saleId = $payload['sale_id'] ?? $payload['id'];
                $sale = \App\Models\Sale::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('id', $saleId)->first();
                $this->assertOwned($sale, $accountId, 'sale');
                $isNew = !$sale;
                $sale = \App\Models\Sale::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)->updateOrCreate(
                    ['id' => $saleId],
                    [
                        'account_id' => $accountId, 'branch_id' => $branchId,
                        'user_id' => $payload['user_id'] ?? null,
                        'subtotal_amount' => $payload['subtotal_amount'] ?? $payload['total_amount'],
                        'total_amount' => $payload['total_amount'], 'tax_amount' => $payload['tax_amount'] ?? 0,
                        'tax_breakdown' => $payload['tax_breakdown'] ?? null,
                        'payment_type' => $payload['payment_type'] ?? null, 'finalized_at' => $payload['finalized_at'] ?? $eventTime,
                        'cash_received' => $payload['cash_received'] ?? null,
                        'change_amount' => $payload['change_amount'] ?? null,
                    ]
                );
                if ($isNew) {
                    foreach ($payload['items'] ?? [] as $item) {
                        $existingItem = \App\Models\SaleItem::with('sale')->find($item['id']);
                        if ($existingItem && (string) $existingItem->sale?->account_id !== (string) $accountId) {
                            throw new \RuntimeException('Sale item ownership conflict.');
                        }
                        \App\Models\SaleItem::updateOrCreate(['id' => $item['id']], [
                            'sale_id' => $sale->id, 'batch_id' => $item['batch_id'], 'inventory_id' => $item['inventory_id'],
                            'drug_id' => $item['drug_id'], 'quantity' => $item['quantity'], 'price' => $item['price'],
                            'unit_cost' => $item['unit_cost'] ?? 0,
                            'line_total' => $item['line_total'] ?? ($item['quantity'] * $item['price']),
                            'tax_amount' => $item['tax_amount'] ?? 0,
                            'tax_breakdown' => $item['tax_breakdown'] ?? null,
                            'requires_prescription' => $item['requires_prescription'] ?? false,
                            'prescription_metadata' => $item['prescription_metadata'] ?? null,
                            'dosage_instructions' => $item['dosage_instructions'] ?? null,
                        ]);
                        \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                            ->where('account_id', $accountId)->where('branch_id', $branchId)
                            ->where('id', $item['inventory_id'])->decrement('quantity_on_hand', (int) $item['quantity']);
                    }

                    if (!empty($payload['customer']['id'])) {
                        $customerData = $payload['customer'];
                        $customer = \App\Models\Customer::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                            ->updateOrCreate(['id' => $customerData['id']], [
                                'account_id' => $accountId,
                                'name' => $customerData['name'] ?? null,
                                'phone' => $customerData['phone'] ?? null,
                                'email' => $customerData['email'] ?? null,
                                'dob' => $customerData['dob'] ?? null,
                            ]);
                        \Illuminate\Support\Facades\DB::table('customer_sales')->updateOrInsert([
                            'account_id' => $accountId, 'sale_id' => $sale->id,
                        ], [
                            'id' => (string) \Illuminate\Support\Str::ulid(),
                            'customer_id' => $customer->id,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }

                    if ((bool) \App\Models\SystemSetting::getValue('accounting_enabled', false)) {
                        app(\App\Services\AccountingService::class)->recordPosSale(
                            $sale->fresh(['items.inventory', 'items.batch']), $accountId, $sale->user_id,
                        );
                    }
                }
                break;
            }

            case 'STOCK_ADJUSTED':
                $inventoryQuery = \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $accountId)
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                    ->when(isset($payload['inventory_id']), fn ($query) => $query->where('id', $payload['inventory_id']))
                    ->where('batch_id', $payload['batch_id']);
                $quantityChange = (int) ($payload['quantity_change'] ?? $payload['change'] ?? 0);
                if ($quantityChange !== 0) {
                    (clone $inventoryQuery)->increment('quantity_on_hand', $quantityChange);
                }
                $updates = array_filter([
                    'location' => $payload['location'] ?? null,
                    'drug_id' => $payload['drug_id'] ?? null,
                    'selling_price' => $payload['selling_price'] ?? null,
                    'cost_price' => $payload['cost_price'] ?? null,
                ], static fn ($value) => $value !== null);
                if ($updates) {
                    $inventoryQuery->update($updates);
                }
                break;

            case 'SALE_RETURNED':
                $returnExists = \App\Models\SalesReturn::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('id', $payload['return_id'])->exists();
                $sale = \App\Models\Sale::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $accountId)
                    ->where('id', $payload['sale_id'])
                    ->first();
                if ($sale && !$returnExists) {
                    $return = \App\Models\SalesReturn::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)->create([
                        'id' => $payload['return_id'], 'sale_id' => $payload['sale_id'],
                        'account_id' => $accountId, 'branch_id' => $branchId,
                        'user_id' => $payload['user_id'] ?? null, 'refund_amount' => $payload['refund_amount'] ?? 0,
                        'refund_method' => $payload['refund_method'] ?? null, 'reason' => $payload['reason'] ?? null,
                        'returned_at' => $payload['returned_at'] ?? $eventTime,
                    ]);
                    foreach ($payload['items'] ?? [] as $item) {
                        \App\Models\SalesReturnItem::updateOrCreate(['id' => $item['id']], [
                            'return_id' => $return->id, 'sale_item_id' => $item['sale_item_id'],
                            'quantity' => $item['quantity'], 'refund_amount' => $item['refund_amount'],
                            'is_restocked' => $item['is_restocked'] ?? false, 'condition' => $item['condition'] ?? null,
                        ]);
                        if ($item['is_restocked'] ?? false) {
                            \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                                ->where('account_id', $accountId)->where('branch_id', $branchId)
                                ->where('id', $item['inventory_id'])->increment('quantity_on_hand', (int) $item['quantity']);
                        }
                    }
                    $sale->increment('total_returned_amount', $payload['refund_amount'] ?? 0);
                    if ((bool) \App\Models\SystemSetting::getValue('accounting_enabled', false)) {
                        app(\App\Services\AccountingService::class)->recordSaleReturn(
                            $return->fresh(['sale', 'items.saleItem.inventory', 'items.saleItem.batch']),
                            $accountId,
                            $return->user_id,
                        );
                    }
                }
                break;

            case 'SALE_ITEM_DOSAGE_AMENDED':
                $item = \App\Models\SaleItem::query()->where('id', $payload['sale_item_id'] ?? null)
                    ->whereHas('sale', fn ($query) => $query->where('account_id', $accountId))->first();
                if (!$item) {
                    throw new \RuntimeException('Dosage amendment references an unknown sale item.');
                }
                $item->update(['dosage_instructions' => $payload['dosage_instructions'] ?? null]);
                break;

            case 'BATCH_REGISTERED':
                $this->assertOwned(\App\Models\Batch::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('id', $payload['batch_id'])->first(), $accountId, 'batch');
                \App\Models\Batch::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->updateOrCreate(['id' => $payload['batch_id']], [
                        'id' => $payload['batch_id'],
                        'account_id' => $accountId,
                        'branch_id' => $branchId,
                        'drug_id' => $payload['drug_id'],
                        'expiry_date' => $payload['expiry_date'] ?? null,
                        'quantity' => $payload['initial_quantity'] ?? 0,
                        'quantity_received' => $payload['initial_quantity'] ?? 0,
                    ]);
                \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->updateOrCreate([
                        'account_id' => $accountId, 'branch_id' => $branchId, 'batch_id' => $payload['batch_id'],
                    ], [
                        'id' => $payload['inventory_id'] ?? \Illuminate\Support\Str::ulid()->toString(),
                        'account_id' => $accountId,
                        'branch_id' => $branchId,
                        'drug_id' => $payload['drug_id'],
                        'batch_id' => $payload['batch_id'],
                        'quantity_on_hand' => $payload['initial_quantity'] ?? 0,
                        'selling_price' => $payload['selling_price'] ?? 0,
                        'cost_price' => $payload['cost_price'] ?? 0,
                        'is_active' => true,
                    ]);
                break;

            case 'STOCK_TRANSFERRED':
                $source = \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $accountId)->where('branch_id', $branchId)
                    ->where('id', $payload['from_inventory_id'])->lockForUpdate()->first();
                if (!$source || $source->quantity_on_hand < (int) $payload['quantity']) {
                    throw new \RuntimeException('Stock transfer projection cannot debit the source inventory.');
                }
                $source->decrement('quantity_on_hand', (int) $payload['quantity']);

                $destination = \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $accountId)->where('branch_id', $payload['to_branch_id'])
                    ->where('drug_id', $payload['drug_id'])->where('batch_id', $payload['batch_id'])->first();
                if ($destination) {
                    $destination->increment('quantity_on_hand', (int) $payload['quantity']);
                } else {
                    \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)->create([
                        'id' => $payload['destination_inventory_id'] ?? \Illuminate\Support\Str::ulid()->toString(),
                        'account_id' => $accountId, 'branch_id' => $payload['to_branch_id'],
                        'drug_id' => $payload['drug_id'], 'batch_id' => $payload['batch_id'],
                        'quantity_on_hand' => $payload['quantity'], 'selling_price' => $payload['selling_price'] ?? 0,
                        'cost_price' => $payload['cost_price'] ?? 0, 'is_active' => true,
                    ]);
                }
                break;

            case 'DRUG_UPSERTED':
                $drugData = $payload['drug'];
                $drug = \App\Models\Drug::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->find($drugData['id']);
                if (!$drug) {
                    $drug = new \App\Models\Drug(['id' => $drugData['id']]);
                }
                $this->assertOwned($drug->exists ? $drug : null, $accountId, 'drug');
                $drug->fill($drugData + ['account_id' => $accountId]);
                $drug->account_id = $accountId;
                $drug->save();
                break;

            case 'DRUG_DELETED':
                \App\Models\Drug::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $accountId)->where('id', $payload['id'])->delete();
                break;

            case 'CUSTOMER_UPSERTED':
                app(\App\Services\Sync\CustomerProjector::class)->apply($event);
                break;

            case 'CUSTOMER_DELETED':
                \App\Models\Customer::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $accountId)->where('id', $payload['id'])->delete();
                break;

            case 'TAX_RATE_UPSERTED':
                $this->assertOwned(\App\Models\TaxRate::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('id', $payload['tax_rate']['id'])->first(), $accountId, 'tax rate');
                \App\Models\TaxRate::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->updateOrCreate(['id' => $payload['tax_rate']['id']], array_replace($payload['tax_rate'], ['account_id' => $accountId]));
                break;

            case 'TAX_RATE_DELETED':
                \App\Models\TaxRate::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $accountId)->where('id', $payload['id'])->delete();
                break;

            case 'BATCH_UPSERTED':
                $this->assertOwned(\App\Models\Batch::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('id', $payload['batch']['id'])->first(), $accountId, 'batch');
                \App\Models\Batch::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->updateOrCreate(['id' => $payload['batch']['id']], array_replace($payload['batch'], [
                        'account_id' => $accountId, 'branch_id' => $branchId,
                    ]));
                break;

            case 'BATCH_DELETED':
                \App\Models\Batch::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $accountId)->where('id', $payload['id'])->delete();
                break;

            case 'INVENTORY_UPSERTED':
                $this->assertOwned(\App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('id', $payload['inventory']['id'])->first(), $accountId, 'inventory');
                \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->updateOrCreate(['id' => $payload['inventory']['id']], array_replace($payload['inventory'], [
                        'account_id' => $accountId, 'branch_id' => $branchId,
                    ]));
                break;

            case 'INVENTORY_DEACTIVATED':
                \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('account_id', $accountId)->where('id', $payload['id'])
                    ->update(['is_active' => false, 'quantity_on_hand' => 0]);
                break;

            case 'SETTINGS_UPDATED':
                foreach ($payload['settings'] ?? [] as $key => $value) {
                    if (!array_key_exists($key, \App\Models\SystemSetting::$defaults)) {
                        continue;
                    }
                    \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert([
                        'account_id' => $accountId, 'key' => $key,
                    ], [
                        'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                        'type' => 'tenant', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                \App\Models\SystemSetting::clearCache($accountId);
                break;

            case 'BRANCH_UPSERTED':
                $this->assertOwned(\App\Models\Branch::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->where('branch_id', $payload['branch']['branch_id'])->first(), $accountId, 'branch');
                \App\Models\Branch::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                    ->updateOrCreate(['branch_id' => $payload['branch']['branch_id']], array_replace($payload['branch'], ['account_id' => $accountId]));
                break;

            default:
                throw new \RuntimeException("Unsupported event type: {$event->event_type}");
            }

            \Illuminate\Support\Facades\DB::table('projected_events')->insert([
                'event_id' => $event->id, 'projected_at' => now(),
            ]);
        });
    }

    private function assertOwned(?object $model, string $accountId, string $label): void
    {
        if ($model && (string) $model->account_id !== (string) $accountId) {
            throw new \RuntimeException("{$label} ownership conflict.");
        }
    }

    /**
     * Trigger a full rebuild of projections from a specific point in time.
     *
     * @param string $accountid
     * @return void
     */
    public function rebuildProjections(string $accountid)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($accountid) {
            $query = \App\Models\EventLedger::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $accountid);

            $events = (clone $query)->get();
            $this->resetReadModels($accountid, $events);

            $query->orderByRaw('global_sequence IS NULL')
                ->orderBy('global_sequence')
                ->orderBy('event_time_utc')
                ->orderBy('device_id')
                ->orderBy('local_sequence')
                ->chunk(100, function ($events) {
                foreach ($events as $event) {
                    $this->projectEvent($event);
                }
            });
        });
    }

    /**
     * Remove only the read-model records represented by the supplied event
     * history. Legacy records without an event are preserved during rollout.
     */
    public function resetReadModels(string $accountId, iterable $events): void
    {
        $ids = ['batch' => [], 'drug' => [], 'customer' => [], 'tax' => []];
        $settingKeys = [];

        foreach ($events as $event) {
            $type = (string) data_get($event, 'event_type');
            $rawPayload = data_get($event, 'event_payload', []);
            $payload = is_string($rawPayload) ? (json_decode($rawPayload, true) ?: []) : (array) $rawPayload;

            if ($type === 'BATCH_REGISTERED') $ids['batch'][] = $payload['batch_id'] ?? null;
            if ($type === 'BATCH_UPSERTED') $ids['batch'][] = data_get($payload, 'batch.id');
            if ($type === 'BATCH_DELETED') $ids['batch'][] = $payload['id'] ?? null;
            if ($type === 'DRUG_UPSERTED') $ids['drug'][] = data_get($payload, 'drug.id');
            if ($type === 'DRUG_DELETED') $ids['drug'][] = $payload['id'] ?? null;
            if ($type === 'CUSTOMER_UPSERTED') $ids['customer'][] = data_get($payload, 'customer.id');
            if ($type === 'CUSTOMER_DELETED') $ids['customer'][] = $payload['id'] ?? null;
            if ($type === 'SALE_FINALIZED') $ids['customer'][] = data_get($payload, 'customer.id');
            if ($type === 'TAX_RATE_UPSERTED') $ids['tax'][] = data_get($payload, 'tax_rate.id');
            if ($type === 'TAX_RATE_DELETED') $ids['tax'][] = $payload['id'] ?? null;
            if ($type === 'SETTINGS_UPDATED') $settingKeys = array_merge($settingKeys, array_keys($payload['settings'] ?? []));
        }

        foreach ($ids as $kind => $values) {
            $ids[$kind] = array_values(array_unique(array_filter($values)));
        }
        $settingKeys = array_values(array_unique($settingKeys));

        \Illuminate\Support\Facades\DB::table('projected_events')
            ->whereIn('event_id', \Illuminate\Support\Facades\DB::table('event_ledger')
                ->where('account_id', $accountId)->select('id'))
            ->delete();
        \App\Models\SalesReturn::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
            ->where('account_id', $accountId)->delete();
        \Illuminate\Support\Facades\DB::table('customer_sales')->where('account_id', $accountId)->delete();
        \App\Models\Sale::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
            ->where('account_id', $accountId)->delete();
        \App\Models\Inventory::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
            ->where('account_id', $accountId)->delete();

        if ($ids['batch']) {
            \App\Models\Batch::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $accountId)->whereIn('id', $ids['batch'])->delete();
        }
        if ($ids['drug']) {
            \App\Models\Drug::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $accountId)->whereIn('id', $ids['drug'])->delete();
        }
        if ($ids['customer']) {
            \App\Models\Customer::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $accountId)->whereIn('id', $ids['customer'])->delete();
        }
        if ($ids['tax']) {
            \App\Models\TaxRate::withoutGlobalScope(\AbacPermissions\Tenancy\TenantScope::class)
                ->where('account_id', $accountId)->whereIn('id', $ids['tax'])->delete();
        }
        if ($settingKeys) {
            \Illuminate\Support\Facades\DB::table('system_settings')
                ->where('account_id', $accountId)->whereIn('key', $settingKeys)->delete();
            \App\Models\SystemSetting::clearCache($accountId);
        }
    }
}
