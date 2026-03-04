<?php

namespace App\Services;

use App\Services\EventLedgerService;
use App\Services\TaxService;
use App\Services\AccountingService;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        try {
            $result = DB::transaction(function () use ($saleData) {
                $saleId = $saleData['id'] ?? Str::ulid()->toString();
                $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
                $customer = $this->resolveOrCreateCustomer($saleData, $accountId);

                // 1. Create Sale Header (Read Model)
                // account_id is automatically assigned via UsesTenant trait
                $sale = \App\Models\Sale::create([
                    'id' => $saleId,
                    // 'account_id' => handled automatically
                    'user_id' => $saleData['user_id'] ?? null,
                    // Fix: Map 'subtotal' from request to 'subtotal_amount' in DB
                    'subtotal_amount' => $saleData['subtotal_amount'] ?? $saleData['subtotal'] ?? 0,
                    'tax_amount' => $saleData['tax_amount'] ?? 0,
                    'total_amount' => $saleData['total_amount'],
                    'payment_type' => $saleData['payment_type'] ?? null,
                    'payment_metadata' => $saleData['payment_metadata'] ?? null,
                    'cash_received' => $saleData['cash_received'] ?? null,
                    'change_amount' => $saleData['change_amount'] ?? null,
                    'finalized_at' => now(),
                ]);

                if ($customer) {
                    $existingLink = DB::table('customer_sales')->where('sale_id', $saleId)->first();
                    if ($existingLink) {
                        DB::table('customer_sales')->where('sale_id', $saleId)->update([
                            'account_id' => $accountId,
                            'customer_id' => $customer->id,
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('customer_sales')->insert([
                            'id' => (string) Str::ulid(),
                            'account_id' => $accountId,
                            'customer_id' => $customer->id,
                            'sale_id' => $saleId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // 2. OPTIMIZED: Batch insert sale items (1 query instead of N)
                $saleItems = collect($saleData['items'])->map(function ($item) use ($saleId) {
                    return [
                        'id' => $item['id'] ?? Str::ulid()->toString(),
                        'sale_id' => $saleId,
                        'batch_id' => $item['batch_id'],
                        'inventory_id' => $item['inventory_id'],
                        'drug_id' => $item['drug_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'line_total' => $item['quantity'] * $item['price'],
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'requires_prescription' => $item['requires_prescription'] ?? false,
                        'prescription_metadata' => json_encode($item['prescription_metadata'] ?? null),
                        'dosage_instructions' => json_encode($item['dosage_instructions'] ?? null),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                \App\Models\SaleItem::insert($saleItems);

                // 3. OPTIMIZED: Batch update inventory (group by inventory_id to handle duplicates)
                $inventoryUpdates = collect($saleData['items'])
                    ->groupBy('inventory_id')
                    ->map(fn($items) => $items->sum('quantity'));

                foreach ($inventoryUpdates as $inventoryId => $totalQty) {
                    // Use Eloquent model for tenant scoping (UsesTenant trait)
                    \App\Models\Inventory::where('id', $inventoryId)
                        ->decrement('quantity_on_hand', $totalQty);
                }

                // 4. Prepare event data to emit AFTER transaction
                $eventData = [
                    'account_id' => $accountId,
                    'device_id' => $saleData['device_id'] ?? (\App\Models\Device::value('device_id') ?? 'unknown'),
                    'actor_user_id' => $saleData['user_id'] ?? null,
                    'event_type' => 'SALE_FINALIZED',
                    'event_payload' => [
                        'sale_id' => $saleId,
                        'items' => $saleData['items'],
                        'total_amount' => $saleData['total_amount'],
                        'tax_amount' => $saleData['tax_amount'] ?? 0,
                        'payment_type' => $saleData['payment_type'],
                        'finalized_at' => now()->toIso8601String(),
                    ]
                ];

                if ((bool) SystemSetting::getValue('accounting_enabled', false)) {
                    $this->accounting->recordPosSale($sale, $accountId, $saleData['user_id'] ?? null);
                }

                return (object) [
                    'sale_id' => $saleId,
                    'event_data' => $eventData,
                ];
            });

            // 5. OPTIMIZED: Emit event AFTER transaction (non-blocking)
            $event = $this->ledger->emitEvent($result->event_data);

            // 6. Invalidate store inventory cache
            app(\App\Services\StoreInventoryService::class)->invalidateCache();

            return (object) [
                'sale_id' => $result->sale_id,
                'event_id' => $event->id,
                'hash' => $event->hash
            ];
        } catch (\Exception $e) {
            // Log error or rethrow as needed
            throw $e;
        }
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
    public function getSale(string $saleId)
    {
        // Use Eloquent to check TenantScope automatically and load items with drug info and user
        $sale = \App\Models\Sale::with(['items.inventory', 'items.drug', 'user', 'customers'])->find($saleId);

        return $sale ? $this->decorateSaleProfit($sale) : null;
    }

    /**
     * Get recent sales (non-paginated) for display in POS.
     */
    public function getRecentSales(int $limit = 10)
    {
        $sales = \App\Models\Sale::query()
            ->with('customers')
            ->with(['items.inventory'])
            ->withExists('returns')
            ->orderBy('finalized_at', 'desc')
            ->limit($limit)
            ->get();

        return $sales->map(fn ($sale) => $this->decorateSaleProfit($sale));
    }

    /**
     * Get paginated sales for a branch with optional filtering and sorting.
     */
    public function getAllSales(int $perPage = 15, array $filters = [], string $sortBy = 'finalized_at', string $sortDirection = 'desc')
    {
        $query = \App\Models\Sale::query()
            ->with('customers')
            ->with(['items.inventory'])
            ->withExists('returns');

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
            $costPrice = (float) ($item->inventory->cost_price ?? 0);
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
