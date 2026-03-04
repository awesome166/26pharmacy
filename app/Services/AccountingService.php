<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\DailyBookClosure;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalesReturn;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingService
{
    public function ensureDefaultAccounts(string $accountId): void
    {
        $defaults = [
            ['code' => '1000', 'name' => 'Cash', 'type' => 'Asset'],
            ['code' => '1010', 'name' => 'Bank', 'type' => 'Asset'],
            ['code' => '1020', 'name' => 'Mobile Money Wallet', 'type' => 'Asset'],
            ['code' => '1030', 'name' => 'Card Settlement', 'type' => 'Asset'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'Asset'],
            ['code' => '1200', 'name' => 'Inventory Asset', 'type' => 'Asset'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'Liability'],
            ['code' => '2100', 'name' => 'Tax Payable', 'type' => 'Liability'],
            ['code' => '3000', 'name' => 'Owner Equity', 'type' => 'Equity'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'Revenue'],
            ['code' => '4010', 'name' => 'Returns & Discounts', 'type' => 'Revenue'],
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'type' => 'Expense'],
            ['code' => '5100', 'name' => 'Operating Expenses', 'type' => 'Expense'],
            ['code' => '5200', 'name' => 'Cash Over/Short', 'type' => 'Expense'],
        ];

        foreach ($defaults as $row) {
            $exists = ChartOfAccount::query()
                ->where('account_id', $accountId)
                ->where('code', $row['code'])
                ->exists();

            if ($exists) {
                continue;
            }

            ChartOfAccount::create([
                'id' => (string) Str::ulid(),
                'account_id' => $accountId,
                'code' => $row['code'],
                'name' => $row['name'],
                'type' => $row['type'],
                'is_group' => false,
                'is_active' => true,
            ]);
        }
    }

    public function getAccountsWithBalances(string $accountId, ?string $asOfDate = null)
    {
        $asOfDate = $asOfDate ?: now()->toDateString();
        $this->ensureDefaultAccounts($accountId);

        $accounts = ChartOfAccount::query()
            ->where('account_id', $accountId)
            ->orderBy('code')
            ->get();

        $rawBalances = JournalEntryLine::query()
            ->selectRaw('chart_of_account_id, COALESCE(SUM(debit - credit), 0) AS raw_balance')
            ->whereHas('journalEntry', function ($q) use ($accountId, $asOfDate) {
                $q->where('account_id', $accountId)
                    ->whereIn('status', ['posted', 'reversed'])
                    ->whereDate('date', '<=', $asOfDate);
            })
            ->groupBy('chart_of_account_id')
            ->pluck('raw_balance', 'chart_of_account_id');

        return $accounts->map(function ($account) use ($rawBalances) {
            $raw = (float) ($rawBalances[$account->id] ?? 0);
            $account->current_balance = $this->normalizeByType($account->type, $raw);
            return $account;
        });
    }

    public function createOrUpdateAccount(?string $id, array $data, string $accountId): ChartOfAccount
    {
        if ($id) {
            $account = ChartOfAccount::query()->where('account_id', $accountId)->findOrFail($id);
            $account->update($data);
            return $account->fresh();
        }

        return ChartOfAccount::create([
            'id' => (string) Str::ulid(),
            'account_id' => $accountId,
            ...$data,
        ]);
    }

    public function createJournalEntry(array $payload, string $accountId): JournalEntry
    {
        $this->assertBalanced($payload['details'] ?? []);
        $date = Carbon::parse($payload['date'])->toDateString();
        $this->assertDateOpen($accountId, $date);

        $attempts = 0;

        while ($attempts < 5) {
            $attempts++;

            try {
                return DB::transaction(function () use ($payload, $accountId, $date) {
                    $entry = JournalEntry::create([
                        'id' => (string) Str::ulid(),
                        'account_id' => $accountId,
                        'entry_number' => $this->generateEntryNumber($accountId, $date),
                        'date' => $date,
                        'description' => trim((string) $payload['description']),
                        'reference' => $payload['reference'] ?? null,
                        'status' => 'draft',
                        'total_amount' => $this->calculateDebitTotal($payload['details']),
                    ]);

                    foreach ($payload['details'] as $line) {
                        JournalEntryLine::create([
                            'id' => (string) Str::ulid(),
                            'journal_entry_id' => $entry->id,
                            'chart_of_account_id' => $line['chart_of_account_id'],
                            'debit' => (float) ($line['debit'] ?? 0),
                            'credit' => (float) ($line['credit'] ?? 0),
                            'reference_type' => $line['reference_type'] ?? null,
                            'reference_id' => $line['reference_id'] ?? null,
                            'memo' => $line['memo'] ?? null,
                        ]);
                    }

                    return $entry->load(['lines.chartOfAccount']);
                });
            } catch (QueryException $e) {
                if (!$this->isEntryNumberCollision($e) || $attempts >= 5) {
                    throw $e;
                }

                usleep(10000 * $attempts);
            }
        }

        throw new \RuntimeException('Unable to reserve a unique journal entry number after multiple retries.');
    }

    public function postEntry(string $entryId, string $accountId, ?string $userId = null): JournalEntry
    {
        $entry = JournalEntry::query()
            ->where('account_id', $accountId)
            ->with('lines')
            ->findOrFail($entryId);

        if ($entry->status !== 'draft') {
            return $entry;
        }

        $this->assertDateOpen($accountId, $entry->date->toDateString());
        $this->assertBalanced($entry->lines->map(fn($l) => [
            'chart_of_account_id' => $l->chart_of_account_id,
            'debit' => $l->debit,
            'credit' => $l->credit,
        ])->all());

        $entry->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by_user_id' => $userId,
        ]);

        return $entry->fresh(['lines.chartOfAccount']);
    }

    public function voidEntry(string $entryId, string $accountId, ?string $userId = null): JournalEntry
    {
        $entry = JournalEntry::query()
            ->where('account_id', $accountId)
            ->with('lines')
            ->findOrFail($entryId);

        if ($entry->status !== 'posted') {
            return $entry;
        }

        $this->assertDateOpen($accountId, $entry->date->toDateString());

        DB::transaction(function () use ($entry, $accountId, $userId) {
            $reversal = $this->createJournalEntry([
                'date' => $entry->date->toDateString(),
                'description' => 'Reversal of ' . $entry->entry_number . ': ' . $entry->description,
                'reference' => 'VOID-REV:' . $entry->entry_number,
                'details' => $entry->lines->map(function ($line) {
                    return [
                        'chart_of_account_id' => $line->chart_of_account_id,
                        'debit' => (float) $line->credit,
                        'credit' => (float) $line->debit,
                        'reference_type' => $line->reference_type,
                        'reference_id' => $line->reference_id,
                        'memo' => 'Auto reversal',
                    ];
                })->all(),
            ], $accountId);

            $this->postEntry($reversal->id, $accountId, $userId);

            $entry->update([
                'status' => 'reversed',
                'voided_at' => now(),
                'voided_by_user_id' => $userId,
            ]);
        });

        return $entry->fresh(['lines.chartOfAccount']);
    }

    public function getBalanceSheet(string $accountId, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();
        $accounts = $this->getAccountsWithBalances($accountId, $date);

        $assets = $accounts->where('type', 'Asset')->values();
        $liabilities = $accounts->where('type', 'Liability')->values();
        $equity = $accounts->where('type', 'Equity')->values();

        return [
            'as_of' => $date,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => round((float) $assets->sum('current_balance'), 2),
            'total_liabilities' => round((float) $liabilities->sum('current_balance'), 2),
            'total_equity' => round((float) $equity->sum('current_balance'), 2),
        ];
    }

    public function getIncomeStatement(string $accountId, string $fromDate, string $toDate): array
    {
        $accounts = ChartOfAccount::query()->where('account_id', $accountId)->get(['id', 'name', 'type', 'code']);

        $rawByAccount = JournalEntryLine::query()
            ->selectRaw('chart_of_account_id, COALESCE(SUM(debit - credit), 0) AS raw_balance')
            ->whereHas('journalEntry', function ($q) use ($accountId, $fromDate, $toDate) {
                $q->where('account_id', $accountId)
                    ->whereIn('status', ['posted', 'reversed'])
                    ->whereBetween('date', [$fromDate, $toDate]);
            })
            ->groupBy('chart_of_account_id')
            ->pluck('raw_balance', 'chart_of_account_id');

        $revenue = $accounts->where('type', 'Revenue')->map(function ($a) use ($rawByAccount) {
            $a->amount = $this->normalizeByType('Revenue', (float) ($rawByAccount[$a->id] ?? 0));
            return $a;
        })->values();

        $expense = $accounts->where('type', 'Expense')->map(function ($a) use ($rawByAccount) {
            $a->amount = $this->normalizeByType('Expense', (float) ($rawByAccount[$a->id] ?? 0));
            return $a;
        })->values();

        $totalRevenue = round((float) $revenue->sum('amount'), 2);
        $totalExpense = round((float) $expense->sum('amount'), 2);

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'revenue' => $revenue,
            'expenses' => $expense,
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpense,
            'net_income' => round($totalRevenue - $totalExpense, 2),
        ];
    }

    public function getTrialBalance(string $accountId, ?string $date = null): array
    {
        $date = $date ?: now()->toDateString();
        $accounts = $this->getAccountsWithBalances($accountId, $date);

        $rows = $accounts->map(function ($account) {
            $balance = (float) $account->current_balance;
            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit' => $balance > 0 ? round($balance, 2) : 0.0,
                'credit' => $balance < 0 ? round(abs($balance), 2) : 0.0,
            ];
        });

        return [
            'as_of' => $date,
            'rows' => $rows->values(),
            'total_debit' => round((float) $rows->sum('debit'), 2),
            'total_credit' => round((float) $rows->sum('credit'), 2),
        ];
    }

    public function closeBooks(string $accountId, string $businessDate, ?string $closedByUserId = null, ?string $notes = null): DailyBookClosure
    {
        $businessDate = Carbon::parse($businessDate)->toDateString();
        $this->ensureDefaultAccounts($accountId);

        $entries = JournalEntry::query()
            ->where('account_id', $accountId)
            ->whereDate('date', $businessDate)
            ->whereIn('status', ['posted', 'reversed']);

        $debits = (float) $entries->withSum('lines', 'debit')->get()->sum('lines_sum_debit');
        $credits = (float) $entries->withSum('lines', 'credit')->get()->sum('lines_sum_credit');
        $count = (int) $entries->count();

        if (round($debits, 2) !== round($credits, 2)) {
            throw new \RuntimeException(
                'Cannot close books on an unbalanced day. Debits: ' . round($debits, 2) . ', Credits: ' . round($credits, 2)
            );
        }

        $closure = DailyBookClosure::query()->updateOrCreate(
            ['account_id' => $accountId, 'business_date' => $businessDate],
            [
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_user_id' => $closedByUserId,
                'notes' => $notes,
                'summary' => [
                    'posted_entries' => $count,
                    'total_debits' => round($debits, 2),
                    'total_credits' => round($credits, 2),
                    'is_balanced' => true,
                ],
            ]
        );

        return $closure;
    }

    public function getLatestClosures(string $accountId, int $limit = 14)
    {
        return DailyBookClosure::query()
            ->where('account_id', $accountId)
            ->orderByDesc('business_date')
            ->limit($limit)
            ->get();
    }

    public function getEntriesForIndex(string $accountId, string $status = 'all', int $perPage = 20)
    {
        $query = JournalEntry::query()
            ->where('account_id', $accountId)
            ->with(['lines.chartOfAccount', 'postedBy'])
            ->orderByDesc('date')
            ->orderByDesc('created_at');

        if (in_array($status, ['draft', 'posted', 'voided', 'reversed'], true)) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function recordPosSale(Sale $sale, string $accountId, ?string $userId = null): ?JournalEntry
    {
        if ((float) $sale->total_amount <= 0) {
            return null;
        }

        $sale->loadMissing(['items.inventory', 'items.batch']);

        $paymentAccountCode = $this->mapPaymentAccountCode($sale->payment_type);
        $paymentAccount = $this->findAccountByCode($accountId, $paymentAccountCode);
        $salesRevenue = $this->findAccountByCode($accountId, '4000');
        $taxPayable = $this->findAccountByCode($accountId, '2100');
        $cogsAccount = $this->findAccountByCode($accountId, '5000');
        $inventoryAsset = $this->findAccountByCode($accountId, '1200');

        $taxAmount = round((float) ($sale->tax_amount ?? 0), 2);
        $totalAmount = round((float) ($sale->total_amount ?? 0), 2);
        $subtotal = round((float) ($sale->subtotal_amount ?? 0), 2);
        if ($subtotal <= 0 && $totalAmount > 0) {
            $subtotal = max(0, round($totalAmount - $taxAmount, 2));
        }

        $details = [[
            'chart_of_account_id' => $paymentAccount->id,
            'debit' => $totalAmount,
            'credit' => 0,
            'reference_type' => Sale::class,
            'reference_id' => (string) $sale->id,
            'memo' => 'Payment capture',
        ]];

        if ($subtotal > 0) {
            $details[] = [
                'chart_of_account_id' => $salesRevenue->id,
                'debit' => 0,
                'credit' => $subtotal,
                'reference_type' => Sale::class,
                'reference_id' => (string) $sale->id,
                'memo' => 'Revenue recognition',
            ];
        }

        if ($taxAmount > 0) {
            $details[] = [
                'chart_of_account_id' => $taxPayable->id,
                'debit' => 0,
                'credit' => $taxAmount,
                'reference_type' => Sale::class,
                'reference_id' => (string) $sale->id,
                'memo' => 'Output tax',
            ];
        }

        $costOfGoods = $this->calculateSaleCost($sale->items);
        if ($costOfGoods > 0) {
            $details[] = [
                'chart_of_account_id' => $cogsAccount->id,
                'debit' => $costOfGoods,
                'credit' => 0,
                'reference_type' => Sale::class,
                'reference_id' => (string) $sale->id,
                'memo' => 'Cost of goods sold',
            ];
            $details[] = [
                'chart_of_account_id' => $inventoryAsset->id,
                'debit' => 0,
                'credit' => $costOfGoods,
                'reference_type' => Sale::class,
                'reference_id' => (string) $sale->id,
                'memo' => 'Inventory relief',
            ];
        }

        $entry = $this->createJournalEntry([
            'date' => optional($sale->finalized_at)->toDateString() ?: now()->toDateString(),
            'description' => 'POS Sale ' . $sale->id,
            'reference' => 'SALE:' . $sale->id,
            'details' => $details,
        ], $accountId);

        return $this->postEntry($entry->id, $accountId, $userId);
    }

    public function recordSaleReturn(SalesReturn $salesReturn, string $accountId, ?string $userId = null): ?JournalEntry
    {
        $refundTotal = round((float) ($salesReturn->refund_amount ?? 0), 2);
        if ($refundTotal <= 0) {
            return null;
        }

        $salesReturn->loadMissing(['sale', 'items.saleItem.inventory', 'items.saleItem.batch']);

        $taxRefund = 0.0;
        foreach ($salesReturn->items as $item) {
            $saleItem = $item->saleItem;
            if (!$saleItem || (int) $saleItem->quantity <= 0) {
                continue;
            }

            $ratio = ((float) $item->quantity) / ((float) $saleItem->quantity);
            $taxRefund += $ratio * (float) ($saleItem->tax_amount ?? 0);
        }
        $taxRefund = round(max(0, $taxRefund), 2);
        $netRefund = max(0, round($refundTotal - $taxRefund, 2));

        $refundAccount = $this->findAccountByCode($accountId, $this->mapPaymentAccountCode($salesReturn->refund_method));
        $returnsDiscounts = $this->findAccountByCode($accountId, '4010');
        $taxPayable = $this->findAccountByCode($accountId, '2100');
        $cogsAccount = $this->findAccountByCode($accountId, '5000');
        $inventoryAsset = $this->findAccountByCode($accountId, '1200');

        $details = [];
        if ($netRefund > 0) {
            $details[] = [
                'chart_of_account_id' => $returnsDiscounts->id,
                'debit' => $netRefund,
                'credit' => 0,
                'reference_type' => SalesReturn::class,
                'reference_id' => (string) $salesReturn->id,
                'memo' => 'Sales return net amount',
            ];
        }

        if ($taxRefund > 0) {
            $details[] = [
                'chart_of_account_id' => $taxPayable->id,
                'debit' => $taxRefund,
                'credit' => 0,
                'reference_type' => SalesReturn::class,
                'reference_id' => (string) $salesReturn->id,
                'memo' => 'Tax reversal',
            ];
        }

        $details[] = [
            'chart_of_account_id' => $refundAccount->id,
            'debit' => 0,
            'credit' => $refundTotal,
            'reference_type' => SalesReturn::class,
            'reference_id' => (string) $salesReturn->id,
            'memo' => 'Refund payout',
        ];

        $restockedCost = $this->calculateRestockedReturnCost($salesReturn);
        if ($restockedCost > 0) {
            $details[] = [
                'chart_of_account_id' => $inventoryAsset->id,
                'debit' => $restockedCost,
                'credit' => 0,
                'reference_type' => SalesReturn::class,
                'reference_id' => (string) $salesReturn->id,
                'memo' => 'Inventory returned',
            ];
            $details[] = [
                'chart_of_account_id' => $cogsAccount->id,
                'debit' => 0,
                'credit' => $restockedCost,
                'reference_type' => SalesReturn::class,
                'reference_id' => (string) $salesReturn->id,
                'memo' => 'COGS reversal',
            ];
        }

        $entry = $this->createJournalEntry([
            'date' => optional($salesReturn->returned_at)->toDateString() ?: now()->toDateString(),
            'description' => 'POS Return ' . $salesReturn->id,
            'reference' => 'RETURN:' . $salesReturn->id,
            'details' => $details,
        ], $accountId);

        return $this->postEntry($entry->id, $accountId, $userId);
    }

    public function createCashMovement(array $payload, string $accountId, ?string $userId = null): JournalEntry
    {
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }

        $movement = $payload['movement'] ?? null;
        if (!in_array($movement, ['in', 'out'], true)) {
            throw new \InvalidArgumentException('Cash movement type must be either in or out.');
        }

        $cashAccount = isset($payload['cash_account_id']) && $payload['cash_account_id']
            ? ChartOfAccount::query()->where('account_id', $accountId)->findOrFail($payload['cash_account_id'])
            : $this->findAccountByCode($accountId, '1000');

        $counterpart = ChartOfAccount::query()
            ->where('account_id', $accountId)
            ->findOrFail($payload['counterpart_account_id']);

        $memo = trim((string) ($payload['memo'] ?? ''));

        $details = $movement === 'in'
            ? [
                [
                    'chart_of_account_id' => $cashAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Cash in',
                ],
                [
                    'chart_of_account_id' => $counterpart->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Counterpart',
                ],
            ]
            : [
                [
                    'chart_of_account_id' => $counterpart->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => 'Counterpart',
                ],
                [
                    'chart_of_account_id' => $cashAccount->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => 'Cash out',
                ],
            ];

        $date = Carbon::parse($payload['date'] ?? now()->toDateString())->toDateString();
        $reference = strtoupper($movement === 'in' ? 'CASH-IN' : 'CASH-OUT') . ':' . Str::ulid();

        $entry = $this->createJournalEntry([
            'date' => $date,
            'description' => $memo !== '' ? $memo : ($movement === 'in' ? 'Cash In' : 'Cash Out'),
            'reference' => $reference,
            'details' => $details,
        ], $accountId);

        return $this->postEntry($entry->id, $accountId, $userId);
    }

    public function getOperationalSummary(string $accountId, string $date): array
    {
        $salesQuery = Sale::query()->where('account_id', $accountId)->whereDate('finalized_at', $date);
        $returnsQuery = SalesReturn::query()->where('account_id', $accountId)->whereDate('returned_at', $date);

        $cashMovements = JournalEntry::query()
            ->where('account_id', $accountId)
            ->where('status', 'posted')
            ->whereDate('date', $date)
            ->where(function ($q) {
                $q->where('reference', 'like', 'CASH-IN:%')
                    ->orWhere('reference', 'like', 'CASH-OUT:%');
            })
            ->get(['reference', 'total_amount']);

        $cashIn = (float) $cashMovements
            ->filter(fn($entry) => str_starts_with((string) $entry->reference, 'CASH-IN:'))
            ->sum('total_amount');
        $cashOut = (float) $cashMovements
            ->filter(fn($entry) => str_starts_with((string) $entry->reference, 'CASH-OUT:'))
            ->sum('total_amount');

        return [
            'date' => $date,
            'sales_total' => round((float) $salesQuery->sum('total_amount'), 2),
            'sales_count' => (int) $salesQuery->count(),
            'returns_total' => round((float) $returnsQuery->sum('refund_amount'), 2),
            'returns_count' => (int) $returnsQuery->count(),
            'cash_in_total' => round($cashIn, 2),
            'cash_out_total' => round($cashOut, 2),
        ];
    }

    protected function assertBalanced(array $details): void
    {
        if (count($details) < 2) {
            throw new \InvalidArgumentException('At least two lines are required.');
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($details as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if ($totalDebit <= 0 || abs($totalDebit - $totalCredit) > 0.01) {
            throw new \InvalidArgumentException('Journal entry must be balanced.');
        }
    }

    protected function assertDateOpen(string $accountId, string $date): void
    {
        $latestClosed = DailyBookClosure::query()
            ->where('account_id', $accountId)
            ->where('status', 'closed')
            ->max('business_date');

        if ($latestClosed && Carbon::parse($date)->lte(Carbon::parse($latestClosed))) {
            throw new \RuntimeException("Books are closed for {$latestClosed}. Reopening is required before posting/voiding earlier dates.");
        }
    }

    protected function generateEntryNumber(string $accountId, string $date): string
    {
        $base = 'JE-' . Carbon::parse($date)->format('Ymd') . '-';
        $last = JournalEntry::query()
            ->where('account_id', $accountId)
            ->whereDate('date', $date)
            ->where('entry_number', 'like', $base . '%')
            ->orderByDesc('entry_number')
            ->value('entry_number');

        $next = 1;
        if (is_string($last) && str_starts_with($last, $base)) {
            $next = ((int) substr($last, strlen($base))) + 1;
        }

        return $base . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected function calculateDebitTotal(array $details): float
    {
        $total = 0.0;
        foreach ($details as $line) {
            $total += (float) ($line['debit'] ?? 0);
        }
        return round($total, 2);
    }

    protected function normalizeByType(string $type, float $raw): float
    {
        if (in_array($type, ['Liability', 'Equity', 'Revenue'])) {
            return round($raw * -1, 2);
        }

        return round($raw, 2);
    }

    protected function findAccountByCode(string $accountId, string $code): ChartOfAccount
    {
        $this->ensureDefaultAccounts($accountId);

        return ChartOfAccount::query()
            ->where('account_id', $accountId)
            ->where('code', $code)
            ->firstOrFail();
    }

    protected function mapPaymentAccountCode(?string $paymentType): string
    {
        return match (strtolower((string) $paymentType)) {
            'momo', 'mobile_money', 'mobile-money' => '1020',
            'card', 'visa', 'mastercard' => '1030',
            'bank' => '1010',
            default => '1000',
        };
    }

    protected function isEntryNumberCollision(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'journal_entries_account_id_entry_number_unique')
            || str_contains($message, 'unique constraint failed: journal_entries.account_id, journal_entries.entry_number');
    }

    protected function calculateSaleCost(iterable $items): float
    {
        $totalCost = 0.0;

        foreach ($items as $item) {
            if (!$item instanceof SaleItem) {
                continue;
            }

            $quantity = max(0, (float) $item->quantity);
            if ($quantity <= 0) {
                continue;
            }

            $unitCost = (float) ($item->inventory?->cost_price ?? 0);
            if ($unitCost <= 0) {
                $unitCost = (float) ($item->batch?->cost_price ?? 0);
            }

            $totalCost += round($unitCost * $quantity, 2);
        }

        return round($totalCost, 2);
    }

    protected function calculateRestockedReturnCost(SalesReturn $salesReturn): float
    {
        $totalCost = 0.0;

        foreach ($salesReturn->items as $item) {
            if (!$item->is_restocked || !$item->saleItem) {
                continue;
            }

            $unitCost = (float) ($item->saleItem->inventory?->cost_price ?? 0);
            if ($unitCost <= 0) {
                $unitCost = (float) ($item->saleItem->batch?->cost_price ?? 0);
            }

            $totalCost += round($unitCost * (float) $item->quantity, 2);
        }

        return round($totalCost, 2);
    }
}
