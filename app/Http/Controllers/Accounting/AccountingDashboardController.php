<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountingDashboardController extends Controller
{
    public function __construct(protected AccountingService $accounting)
    {
    }

    public function index(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $asOf = $request->input('date', now()->toDateString());

        $this->accounting->ensureDefaultAccounts($accountId);
        $balanceSheet = $this->accounting->getBalanceSheet($accountId, $asOf);
        $entries = $this->accounting->getEntriesForIndex($accountId, 'all', 5);
        $closures = $this->accounting->getLatestClosures($accountId, 7);
        $operations = $this->accounting->getOperationalSummary($accountId, $asOf);
        $accounts = ChartOfAccount::query()
            ->where('account_id', $accountId)
            ->where('is_group', false)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);

        return Inertia::render('Accounting/Dashboard', [
            'balanceSheet' => $balanceSheet,
            'recentEntries' => $entries->items(),
            'closures' => $closures,
            'operations' => $operations,
            'accounts' => $accounts,
            'filters' => ['date' => $asOf],
        ]);
    }
}
