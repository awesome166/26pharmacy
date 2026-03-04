<?php

namespace Tests\Feature\Accounting;

use App\Models\Batch;
use App\Models\Drug;
use App\Models\Inventory;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $service;
    protected string $accountId;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $account = \AbacPermissions\Models\Account::create([
            'name' => 'Accounting Test Account',
            'slug' => 'accounting-test-account',
        ]);

        $this->accountId = (string) $account->id;

        $this->mock(\AbacPermissions\Tenancy\TenantContext::class, function ($mock) {
            $mock->shouldReceive('getAccountId')->andReturn($this->accountId);
        });

        $this->user = User::factory()->create();
        $this->service = app(AccountingService::class);
        $this->service->ensureDefaultAccounts($this->accountId);
    }

    #[Test]
    public function it_posts_pos_sale_with_revenue_tax_cogs_and_inventory_lines(): void
    {
        [$sale, $saleItem] = $this->seedSaleData(costPrice: 5.00, quantity: 2, lineTotal: 20.00, tax: 2.00);

        $entry = $this->service->recordPosSale($sale, $this->accountId, $this->user->id);

        $this->assertNotNull($entry);
        $this->assertSame('posted', $entry->status);

        $linesByCode = $entry->lines
            ->mapWithKeys(fn ($line) => [$line->chartOfAccount->code => $line]);

        $this->assertEquals(22.00, (float) $linesByCode['1000']->debit); // cash
        $this->assertEquals(20.00, (float) $linesByCode['4000']->credit); // revenue
        $this->assertEquals(2.00, (float) $linesByCode['2100']->credit); // tax
        $this->assertEquals(10.00, (float) $linesByCode['5000']->debit); // cogs (2 * 5)
        $this->assertEquals(10.00, (float) $linesByCode['1200']->credit); // inventory asset relief

        $this->assertSame((string) $sale->id, (string) $linesByCode['5000']->reference_id);
    }

    #[Test]
    public function it_posts_return_with_inventory_and_cogs_reversal_when_restocked(): void
    {
        [$sale, $saleItem] = $this->seedSaleData(costPrice: 5.00, quantity: 2, lineTotal: 20.00, tax: 2.00);

        $salesReturn = SalesReturn::create([
            'id' => (string) Str::ulid(),
            'sale_id' => $sale->id,
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'refund_amount' => 11.00,
            'refund_method' => 'cash',
            'reason' => 'Damaged pack',
            'returned_at' => now(),
        ]);

        SalesReturnItem::create([
            'id' => (string) Str::ulid(),
            'return_id' => $salesReturn->id,
            'sale_item_id' => $saleItem->id,
            'quantity' => 1,
            'refund_amount' => 11.00,
            'is_restocked' => true,
            'condition' => 'sealed',
        ]);

        $entry = $this->service->recordSaleReturn($salesReturn, $this->accountId, $this->user->id);

        $this->assertNotNull($entry);
        $this->assertSame('posted', $entry->status);

        $linesByCode = $entry->lines
            ->mapWithKeys(fn ($line) => [$line->chartOfAccount->code => $line]);

        $this->assertEquals(10.00, (float) $linesByCode['4010']->debit); // net refund
        $this->assertEquals(1.00, (float) $linesByCode['2100']->debit); // tax reversal
        $this->assertEquals(11.00, (float) $linesByCode['1000']->credit); // cash paid out
        $this->assertEquals(5.00, (float) $linesByCode['1200']->debit); // inventory restored
        $this->assertEquals(5.00, (float) $linesByCode['5000']->credit); // cogs reversed
    }

    #[Test]
    public function voiding_a_posted_entry_creates_reversal_and_keeps_balances_neutral(): void
    {
        $cash = $this->findAccountId('1000');
        $expense = $this->findAccountId('5100');

        $entry = $this->service->createJournalEntry([
            'date' => now()->toDateString(),
            'description' => 'Stationery purchase',
            'reference' => 'EXP:001',
            'details' => [
                ['chart_of_account_id' => $expense, 'debit' => 50, 'credit' => 0],
                ['chart_of_account_id' => $cash, 'debit' => 0, 'credit' => 50],
            ],
        ], $this->accountId);

        $posted = $this->service->postEntry($entry->id, $this->accountId, $this->user->id);
        $voided = $this->service->voidEntry($posted->id, $this->accountId, $this->user->id);

        $this->assertSame('reversed', $voided->status);

        $reversal = JournalEntry::query()
            ->where('account_id', $this->accountId)
            ->where('reference', 'VOID-REV:' . $posted->entry_number)
            ->where('status', 'posted')
            ->first();

        $this->assertNotNull($reversal);

        $originalLines = JournalEntryLine::query()->where('journal_entry_id', $posted->id)->orderBy('id')->get()->values();
        $reversalLines = JournalEntryLine::query()->where('journal_entry_id', $reversal->id)->orderBy('id')->get()->values();

        $this->assertCount($originalLines->count(), $reversalLines);

        foreach ($originalLines as $index => $line) {
            $this->assertEquals((float) $line->debit, (float) $reversalLines[$index]->credit);
            $this->assertEquals((float) $line->credit, (float) $reversalLines[$index]->debit);
        }

        $trial = $this->service->getTrialBalance($this->accountId, now()->toDateString());
        $rowsByCode = collect($trial['rows'])->keyBy('code');
        $this->assertEquals(0.0, (float) ($rowsByCode['1000']['debit'] ?? 0));
        $this->assertEquals(0.0, (float) ($rowsByCode['1000']['credit'] ?? 0));
        $this->assertEquals(0.0, (float) ($rowsByCode['5100']['debit'] ?? 0));
        $this->assertEquals(0.0, (float) ($rowsByCode['5100']['credit'] ?? 0));
    }

    #[Test]
    public function it_prevents_closing_books_when_day_is_unbalanced(): void
    {
        $entry = JournalEntry::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'entry_number' => 'JE-' . now()->format('Ymd') . '-0999',
            'date' => now()->toDateString(),
            'status' => 'posted',
            'description' => 'Broken entry',
            'reference' => 'TEST:UNBALANCED',
            'total_amount' => 50,
            'posted_at' => now(),
            'posted_by_user_id' => $this->user->id,
        ]);

        JournalEntryLine::create([
            'id' => (string) Str::ulid(),
            'journal_entry_id' => $entry->id,
            'chart_of_account_id' => $this->findAccountId('1000'),
            'debit' => 50,
            'credit' => 0,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot close books on an unbalanced day.');

        $this->service->closeBooks($this->accountId, now()->toDateString(), $this->user->id, 'Attempted close');
    }

    #[Test]
    public function it_generates_next_entry_number_from_highest_sequence_not_row_count(): void
    {
        $date = now()->toDateString();

        JournalEntry::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'entry_number' => 'JE-' . now()->format('Ymd') . '-0001',
            'date' => $date,
            'status' => 'draft',
            'description' => 'Existing 1',
            'total_amount' => 10,
        ]);

        JournalEntry::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'entry_number' => 'JE-' . now()->format('Ymd') . '-0003',
            'date' => $date,
            'status' => 'draft',
            'description' => 'Existing 3',
            'total_amount' => 10,
        ]);

        $entry = $this->service->createJournalEntry([
            'date' => $date,
            'description' => 'Should be 0004',
            'details' => [
                ['chart_of_account_id' => $this->findAccountId('5100'), 'debit' => 10, 'credit' => 0],
                ['chart_of_account_id' => $this->findAccountId('1000'), 'debit' => 0, 'credit' => 10],
            ],
        ], $this->accountId);

        $this->assertStringEndsWith('-0004', $entry->entry_number);
    }

    protected function seedSaleData(float $costPrice, int $quantity, float $lineTotal, float $tax): array
    {
        $drug = Drug::create([
            'id' => (string) Str::ulid(),
            'name' => 'Test Drug',
        ]);

        $batch = Batch::create([
            'id' => (string) Str::ulid(),
            'drug_id' => $drug->id,
            'lot_number' => 'LOT-001',
            'quantity' => 100,
            'quantity_recieved' => 100,
            'cost_price' => $costPrice,
        ]);

        $inventory = Inventory::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'drug_id' => $drug->id,
            'batch_id' => $batch->id,
            'selling_price' => 10,
            'cost_price' => $costPrice,
            'reorder_level' => 1,
            'location' => 'A1',
            'is_active' => true,
            'quantity_on_hand' => 100,
        ]);

        $sale = Sale::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->accountId,
            'user_id' => $this->user->id,
            'subtotal_amount' => $lineTotal,
            'tax_amount' => $tax,
            'total_amount' => $lineTotal + $tax,
            'payment_type' => 'cash',
            'finalized_at' => now(),
        ]);

        $saleItem = SaleItem::create([
            'id' => (string) Str::ulid(),
            'sale_id' => $sale->id,
            'batch_id' => $batch->id,
            'inventory_id' => $inventory->id,
            'drug_id' => $drug->id,
            'quantity' => $quantity,
            'price' => $lineTotal / $quantity,
            'line_total' => $lineTotal,
            'tax_amount' => $tax,
        ]);

        return [$sale, $saleItem];
    }

    protected function findAccountId(string $code): string
    {
        $account = \App\Models\ChartOfAccount::query()
            ->where('account_id', $this->accountId)
            ->where('code', $code)
            ->firstOrFail();

        return (string) $account->id;
    }
}
