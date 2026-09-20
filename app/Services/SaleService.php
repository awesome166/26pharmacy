<?php

namespace App\Services;

use App\Services\EventLedgerService;
use App\Services\TaxService;
use App\Services\AccountingService;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    protected $ledger;
    protected $tax;
    protected $accounting;

    public function __construct(EventLedgerService $ledger, TaxService $tax, AccountingService $accounting)
    {
        $this->ledger = $ledger;
        $this->tax = $tax;
        $this->accounting = $accounting;
    }

    /**
     * Process a new sale transaction.
     * OPTIMIZED: Uses batch inserts and updates to reduce query count.
     */
    public function processSale(array $saleData)
    {
        $result = DB::transaction(function () use ($saleData) {
            $saleId = $saleData['id'] ?? Str::ulid()->toString();
            $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
            $device = app(DeviceContextService::class)->currentDevice($accountId, $saleData['device_id'] ?? null);
            $branch = DB::table('branches')->where('account_id', $accountId)
                ->where('branch_id', $device->branch_id)->first();

            $submittedItems = collect($saleData['items'])->values();
            $inventoryIds = $submittedItems->pluck('inventory_id')->unique()->values();
            $inventories = \App\Models\Inventory::query()
                ->with(['drug', 'batch'])
                ->where('branch_id', $device->branch_id)
                ->whereIn('id', $inventoryIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn ($inventory) => (string) $inventory->id);

            $requestedQuantities = $submittedItems->groupBy('inventory_id')
                ->map(fn ($items) => (int) $items->sum('quantity'));

            foreach ($requestedQuantities as $inventoryId => $quantity) {
                $inventory = $inventories->get((string) $inventoryId);
                if (!$inventory || !$inventory->is_active) {
                    throw ValidationException::withMessages(['items' => "Inventory {$inventoryId} is not available at this branch."]);
                }
                if ((int) $inventory->quantity_on_hand < $quantity) {
                    throw ValidationException::withMessages(['items' => "Insufficient stock for inventory {$inventoryId}."]);
                }
                if (!$inventory->batch || !$inventory->batch->is_active
                    || ($inventory->batch->expiry_date && $inventory->batch->expiry_date->startOfDay()->isBefore(now()->startOfDay()))) {
                    throw ValidationException::withMessages(['items' => "Inventory {$inventoryId} belongs to an expired or inactive batch."]);
                }
            }

            $requirePrescription = (bool) SystemSetting::getValue('sales_require_prescription', false);
            $prepared = $submittedItems->map(function (array $item, int $index) use ($inventories, $saleId, $requirePrescription) {
                $inventory = $inventories->get((string) $item['inventory_id']);
                if (!empty($item['batch_id']) && (string) $item['batch_id'] !== (string) $inventory->batch_id) {
                    throw ValidationException::withMessages(["items.{$index}.batch_id" => 'Batch does not match the selected inventory.']);
                }
                if (!empty($item['drug_id']) && (string) $item['drug_id'] !== (string) $inventory->drug_id) {
                    throw ValidationException::withMessages(["items.{$index}.drug_id" => 'Drug does not match the selected inventory.']);
                }

                $requiresPrescription = (bool) ($inventory->drug?->is_prescription || $inventory->drug?->is_controlled || $inventory->drug?->is_narcotic);
                if (($inventory->drug?->is_controlled || $inventory->drug?->is_narcotic || ($requirePrescription && $requiresPrescription))
                    && empty($item['prescription_metadata'])) {
                    throw ValidationException::withMessages(["items.{$index}.prescription_metadata" => 'Prescription details are required for this medicine.']);
                }

                $quantity = (int) $item['quantity'];
                $price = round((float) $inventory->selling_price, 2);

                return [
                    'key' => (string) $index,
                    'id' => $item['id'] ?? Str::ulid()->toString(),
                    'sale_id' => $saleId,
                    'batch_id' => (string) $inventory->batch_id,
                    'inventory_id' => (string) $inventory->id,
                    'drug_id' => (string) $inventory->drug_id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'unit_cost' => round((float) $inventory->cost_price, 2),
                    'line_total' => round($quantity * $price, 2),
                    'category' => $inventory->drug?->drug_class,
                    'requires_prescription' => $requiresPrescription,
                    'prescription_metadata' => $item['prescription_metadata'] ?? null,
                    'dosage_instructions' => $item['dosage_instructions'] ?? null,
                ];
            });

            $lineTaxes = collect($this->tax->calculateSaleLines(
                $prepared->map(fn ($item) => [
                    'key' => $item['key'], 'amount' => $item['line_total'], 'category' => $item['category'],
                ])->all(),
                $branch->tax_jurisdiction ?? 'Default',
            ))->keyBy('key');

            $prepared = $prepared->map(function ($item) use ($lineTaxes) {
                $tax = $lineTaxes->get($item['key'], ['tax_amount' => 0, 'breakdown' => []]);
                $item['tax_amount'] = round((float) $tax['tax_amount'], 2);
                $item['tax_breakdown'] = $tax['breakdown'];
                unset($item['key'], $item['category']);
                return $item;
            });

            $subtotal = round((float) $prepared->sum('line_total'), 2);
            $taxAmount = round((float) $prepared->sum('tax_amount'), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);
            $cashReceived = $saleData['payment_type'] === 'cash' ? round((float) ($saleData['cash_received'] ?? 0), 2) : null;
            if ($cashReceived !== null && $cashReceived < $totalAmount) {
                throw ValidationException::withMessages(['cash_received' => 'Cash received is less than the server-calculated total.']);
            }

            $saleTaxBreakdown = $this->aggregateTaxBreakdown($prepared->pluck('tax_breakdown')->all());
            $sale = \App\Models\Sale::create([
                'id' => $saleId,
                'account_id' => $accountId,
                'branch_id' => $device->branch_id,
                'user_id' => $saleData['user_id'],
                'subtotal_amount' => $subtotal,
                'tax_amount' => $taxAmount,
                'tax_breakdown' => $saleTaxBreakdown,
                'total_amount' => $totalAmount,
                'payment_type' => $saleData['payment_type'],
                'payment_metadata' => $saleData['payment_metadata'] ?? null,
                'cash_received' => $cashReceived,
                'change_amount' => $cashReceived !== null ? round($cashReceived - $totalAmount, 2) : null,
                'finalized_at' => now(),
            ]);

            $customer = $this->resolveOrCreateCustomer($saleData, $accountId);
            if ($customer) {
                DB::table('customer_sales')->insert([
                    'id' => (string) Str::ulid(), 'account_id' => $accountId,
                    'customer_id' => $customer->id, 'sale_id' => $saleId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $saleItems = $prepared->map(function ($item) {
                return array_merge($item, [
                    'tax_breakdown' => json_encode($item['tax_breakdown'], JSON_THROW_ON_ERROR),
                    'prescription_metadata' => json_encode($item['prescription_metadata'], JSON_THROW_ON_ERROR),
                    'dosage_instructions' => json_encode($item['dosage_instructions'], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            })->all();
            \App\Models\SaleItem::insert($saleItems);

            foreach ($requestedQuantities as $inventoryId => $quantity) {
                $inventories->get((string) $inventoryId)->decrement('quantity_on_hand', $quantity);
            }

            $eventItems = $prepared->values()->all();
            $event = $this->ledger->emitEvent([
                'account_id' => $accountId,
                'device_id' => $device->device_id,
                'actor_user_id' => $saleData['user_id'],
                'event_type' => 'SALE_FINALIZED',
                'project_locally' => false,
                'event_payload' => [
                    'sale_id' => $saleId, 'items' => $eventItems,
                    'user_id' => $saleData['user_id'],
                    'customer' => $customer?->only(['id', 'name', 'phone', 'email', 'dob']),
                    'subtotal_amount' => $subtotal, 'tax_amount' => $taxAmount,
                    'tax_breakdown' => $saleTaxBreakdown, 'total_amount' => $totalAmount,
                    'payment_type' => $saleData['payment_type'],
                    'cash_received' => $cashReceived, 'change_amount' => $sale->change_amount,
                    'finalized_at' => now()->toIso8601String(),
                ],
            ]);

            $sale->load(['items.inventory', 'items.batch', 'customers']);
            if ((bool) SystemSetting::getValue('accounting_enabled', false)) {
                $this->accounting->recordPosSale($sale, $accountId, $saleData['user_id']);
            }

            return (object) ['sale' => $sale, 'event' => $event];
        });

        app(StoreInventoryService::class)->invalidateCache();

        return (object) [
            'sale_id' => $result->sale->id,
            'event_id' => $result->event->id,
            'hash' => $result->event->hash,
            'sale' => $result->sale,
        ];
    }

    protected function aggregateTaxBreakdown(array $lineBreakdowns): array
    {
        return collect($lineBreakdowns)->flatten(1)
            ->groupBy('tax_rate_id')
            ->map(function ($lines) {
                $first = $lines->first();
                $first['taxable_base'] = round((float) $lines->sum('taxable_base'), 2);
                $first['tax_amount'] = round((float) $lines->sum('tax_amount'), 2);
                return $first;
            })->values()->all();
    }

    protected function resolveOrCreateCustomer(array $saleData, string|int|null $accountId): ?\App\Models\Customer
    {
        $customerId = isset($saleData['customer_id']) ? trim((string) $saleData['customer_id']) : null;
        $name = isset($saleData['customer_name']) ? trim((string) $saleData['customer_name']) : null;
        $phone = isset($saleData['customer_phone']) ? trim((string) $saleData['customer_phone']) : null;
        $email = isset($saleData['customer_email']) ? strtolower(trim((string) $saleData['customer_email'])) : null;
        $dob = !empty($saleData['customer_dob']) ? $saleData['customer_dob'] : null;

        $hasCustomerInfo = !empty($customerId) || !empty($name) || !empty($phone) || !empty($email) || !empty($dob);
        if (!$hasCustomerInfo) {
            return null;
        }

        if (!empty($customerId)) {
            $existingById = \App\Models\Customer::query()
                ->where('account_id', $accountId)
                ->where('id', $customerId)
                ->first();

            if ($existingById) {
                $existingById->fill([
                    'name' => $name ?: $existingById->name,
                    'phone' => $phone ?: $existingById->phone,
                    'email' => $email ?: $existingById->email,
                    'dob' => $dob ?: $existingById->dob,
                ]);
                $existingById->save();

                return $existingById;
            }
        }

        $query = \App\Models\Customer::query()->where('account_id', $accountId);

        if (!empty($phone)) {
            $query->where('phone', $phone);
        } elseif (!empty($email)) {
            $query->where('email', $email);
        } else {
            $query->where('name', $name);
            if (!empty($dob)) {
                $query->whereDate('dob', $dob);
            }
        }

        $customer = $query->first();

        if ($customer) {
            $customer->fill([
                'name' => $name ?: $customer->name,
                'phone' => $phone ?: $customer->phone,
                'email' => $email ?: $customer->email,
                'dob' => $dob ?: $customer->dob,
            ]);
            $customer->save();

            return $customer;
        }

        return \App\Models\Customer::create([
            'id' => \Illuminate\Support\Str::ulid()->toString(),
            'account_id' => $accountId,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'dob' => $dob,
        ]);
    }

    /**
     * Get a single sale by ID from the Read Model.
     */
    public function getSale(string $saleId, ?string $branchId = null)
    {
        // Use Eloquent to check TenantScope automatically and load items with drug info and user
        $sale = \App\Models\Sale::with(['items.inventory', 'items.drug', 'user', 'customers'])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->find($saleId);

        return $sale ? $this->decorateSaleProfit($sale) : null;
    }

    /**
     * Get recent sales (non-paginated) for display in POS.
     */
    public function getRecentSales(int $limit = 10, ?string $branchId = null)
    {
        $sales = \App\Models\Sale::query()
            ->with('customers')
            ->with(['items.inventory'])
            ->withExists('returns')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('finalized_at', 'desc')
            ->limit($limit)
            ->get();

        return $sales->map(fn ($sale) => $this->decorateSaleProfit($sale));
    }

    /**
     * Get paginated sales for a branch with optional filtering and sorting.
     */
    public function getAllSales(int $perPage = 15, array $filters = [], string $sortBy = 'finalized_at', string $sortDirection = 'desc', ?string $branchId = null)
    {
        $query = \App\Models\Sale::query()
            ->with('customers')
            ->with(['items.inventory'])
            ->withExists('returns')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

        // Apply date range filters
        if (!empty($filters['start_date'])) {
            $query->whereDate('finalized_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('finalized_at', '<=', $filters['end_date']);
        }

        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('id', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('payment_type', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Validate and apply sorting
        $allowedSortFields = ['finalized_at', 'total_amount', 'payment_type'];
        $sortField = in_array($sortBy, $allowedSortFields) ? $sortBy : 'finalized_at';
        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $paginated = $query->orderBy($sortField, $sortDir)->paginate($perPage);
        $paginated->setCollection(
            $paginated->getCollection()->map(fn ($sale) => $this->decorateSaleProfit($sale))
        );

        return $paginated;
    }

    protected function decorateSaleProfit(\App\Models\Sale $sale): \App\Models\Sale
    {
        $totalCost = 0.0;
        foreach ($sale->items as $item) {
            $costPrice = (float) ($item->unit_cost ?? 0);
            if ($costPrice <= 0) {
                $costPrice = (float) ($item->inventory->cost_price ?? 0);
            }
            $totalCost += $costPrice * (float) $item->quantity;
        }

        $netSales = (float) ($sale->subtotal_amount ?? 0);
        if ($netSales <= 0) {
            $netSales = max(0, (float) $sale->total_amount - (float) $sale->tax_amount);
        }

        $grossProfit = round($netSales - $totalCost, 2);
        $margin = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0.0;

        $sale->setAttribute('total_cost', round($totalCost, 2));
        $sale->setAttribute('gross_profit', $grossProfit);
        $sale->setAttribute('gross_margin', $margin);

        return $sale;
    }
}
