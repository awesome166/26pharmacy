<?php

namespace Tests\Feature;

use AbacPermissions\Models\Account;
use AbacPermissions\Tenancy\TenantContext;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Drug;
use App\Models\FinancialDaySummary;
use App\Models\Inventory;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\SystemSetting;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\ReportingService;
use App\Services\SaleService;
use App\Services\StoreInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PharmacyIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;
    private User $user;
    private Branch $branch;
    private Device $device;
    private Drug $drug;
    private Batch $batch;
    private Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['sync.role' => 'child']);

        $this->account = Account::create(['name' => 'Integrity Pharmacy', 'slug' => 'integrity-pharmacy']);
        app(TenantContext::class)->setAccount($this->account);
        $this->user = User::factory()->create();
        $this->branch = Branch::create([
            'branch_id' => (string) Str::ulid(), 'account_id' => $this->account->id,
            'name' => 'Accra Main', 'code' => 'ACC', 'tax_jurisdiction' => 'Default',
            'timezone' => 'Africa/Accra', 'is_active' => true,
        ]);
        $this->device = Device::create([
            'device_id' => (string) Str::ulid(), 'account_id' => $this->account->id,
            'branch_id' => $this->branch->branch_id, 'device_name' => 'Till 1', 'trust_status' => 'active',
        ]);
        config(['sync.client_id' => $this->device->device_id]);

        $this->drug = Drug::create([
            'id' => (string) Str::ulid(), 'account_id' => $this->account->id,
            'name' => 'Test Medicine', 'drug_class' => 'otc',
        ]);
        $this->batch = Batch::create([
            'id' => (string) Str::ulid(), 'account_id' => $this->account->id,
            'branch_id' => $this->branch->branch_id, 'drug_id' => $this->drug->id,
            'lot_number' => 'LOT-1', 'expiry_date' => now()->addYear(), 'is_active' => true,
        ]);
        $this->inventory = Inventory::create([
            'id' => (string) Str::ulid(), 'account_id' => $this->account->id,
            'branch_id' => $this->branch->branch_id, 'drug_id' => $this->drug->id,
            'batch_id' => $this->batch->id, 'selling_price' => 10,
            'cost_price' => 4, 'quantity_on_hand' => 10, 'is_active' => true,
        ]);
    }

    #[Test]
    public function sale_uses_server_prices_compound_tax_and_cost_snapshots(): void
    {
        SystemSetting::setValue('sales_add_tax', true);
        $this->tax('VAT', 10, 0, 50, 1, false);
        $this->tax('Health Levy', 5, 0, 50, 2, true);

        $receipt = app(SaleService::class)->processSale([
            'user_id' => $this->user->id,
            'device_id' => $this->device->device_id,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'payment_type' => 'cash',
            'cash_received' => 100,
            'items' => [[
                'inventory_id' => $this->inventory->id,
                'batch_id' => $this->batch->id,
                'drug_id' => $this->drug->id,
                'quantity' => 2,
                'price' => 0.01,
            ]],
        ]);

        $sale = $receipt->sale->fresh('items');
        $item = $sale->items->first();
        $this->assertSame(20.0, (float) $sale->subtotal_amount);
        $this->assertSame(3.1, (float) $sale->tax_amount);
        $this->assertSame(23.1, (float) $sale->total_amount);
        $this->assertSame(10.0, (float) $item->price);
        $this->assertSame(4.0, (float) $item->unit_cost);
        $this->assertCount(2, $item->tax_breakdown);
        $this->assertSame(8, (int) $this->inventory->fresh()->quantity_on_hand);
    }

    #[Test]
    public function expired_stock_is_hidden_and_rejected_by_checkout(): void
    {
        $this->batch->update(['expiry_date' => now()->subDay()]);

        $inventory = app(StoreInventoryService::class)->getStoreInventory(50, null, $this->branch->branch_id);
        $this->assertSame(0, $inventory->total());

        $this->expectException(ValidationException::class);
        app(SaleService::class)->processSale([
            'user_id' => $this->user->id, 'device_id' => $this->device->device_id,
            'payment_type' => 'cash', 'cash_received' => 100,
            'items' => [['inventory_id' => $this->inventory->id, 'quantity' => 1]],
        ]);
    }

    #[Test]
    public function daily_report_is_branch_scoped_nets_returns_and_does_not_persist_on_read(): void
    {
        SystemSetting::setValue('sales_add_tax', true);
        $this->tax('VAT', 10, null, null, 1, false);
        $receipt = app(SaleService::class)->processSale([
            'user_id' => $this->user->id, 'device_id' => $this->device->device_id,
            'payment_type' => 'cash', 'cash_received' => 100,
            'items' => [['inventory_id' => $this->inventory->id, 'quantity' => 2]],
        ]);
        $sale = $receipt->sale->fresh('items');
        $saleItem = $sale->items->first();
        $return = SalesReturn::create([
            'id' => (string) Str::ulid(), 'account_id' => $this->account->id,
            'branch_id' => $this->branch->branch_id, 'sale_id' => $sale->id,
            'user_id' => $this->user->id, 'refund_amount' => 11,
            'refund_method' => 'cash', 'reason' => 'Returned', 'returned_at' => now(),
        ]);
        SalesReturnItem::create([
            'id' => (string) Str::ulid(), 'return_id' => $return->id,
            'sale_item_id' => $saleItem->id, 'quantity' => 1,
            'refund_amount' => 11, 'is_restocked' => true,
        ]);

        $summary = app(ReportingService::class)->getDailySummary(now()->toDateString(), $this->branch->branch_id);
        $this->assertSame(10.0, (float) $summary->net_sales);
        $this->assertSame(1.0, (float) $summary->tax_collected);
        $this->assertSame(11.0, (float) $summary->returns_amount);
        $this->assertSame(4.0, (float) $summary->total_cost);
        $this->assertSame(6.0, (float) $summary->gross_profit);
        $this->assertSame(0, FinancialDaySummary::count());

        app(ReportingService::class)->getDailySummary(now()->toDateString(), $this->branch->branch_id, true);
        $this->assertDatabaseHas('financial_day_summaries', [
            'account_id' => $this->account->id,
            'branch_id' => $this->branch->branch_id,
            'day' => now()->toDateString(),
            'returns_amount' => 11,
        ]);

        $path = app(ReportingService::class)->exportRegulatoryData(
            now()->toDateString(), now()->toDateString(), $this->branch->branch_id,
        );
        try {
            $csv = file_get_contents($path);
            $this->assertStringContainsString('"Record Type","Reference ID","Sale ID"', $csv);
            $this->assertStringContainsString("\nSALE,", $csv);
            $this->assertStringContainsString("\nRETURN,", $csv);
        } finally {
            @unlink($path);
        }
    }

    private function tax(string $name, float $percentage, ?float $min, ?float $max, int $order, bool $compound): TaxRate
    {
        return TaxRate::create([
            'id' => (string) Str::ulid(), 'account_id' => $this->account->id,
            'jurisdiction' => 'Default', 'tax_name' => $name, 'percentage' => $percentage,
            'minimum_taxable_amount' => $min, 'maximum_taxable_amount' => $max,
            'calculation_order' => $order, 'is_compound' => $compound,
            'tax_type' => 'sales', 'applicable_categories' => ['all'],
            'effective_from' => now()->subDay(), 'is_active' => true,
        ]);
    }
}
