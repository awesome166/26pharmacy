<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ChartOfAccountController extends Controller
{
    public function __construct(protected AccountingService $accounting)
    {
    }

    public function index(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $asOf = $request->input('date', now()->toDateString());
        $accounts = $this->accounting->getAccountsWithBalances($accountId, $asOf);

        $roots = $accounts->whereNull('parent_id')->values();
        $tree = $roots->map(fn ($root) => $this->attachChildren($root, $accounts))->values();

        if ($request->wantsJson()) {
            return response()->json($accounts->values());
        }

        return Inertia::render('Accounting/Accounts/Index', [
            'accounts' => $tree,
            'filters' => ['date' => $asOf],
        ]);
    }

    public function store(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $data = $this->validatePayload($request, $accountId);
        $this->accounting->createOrUpdateAccount(null, $data, $accountId);
        return back()->with('success', 'Account created.');
    }

    public function update(Request $request, ChartOfAccount $account)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_if($account->account_id !== $accountId, 403);

        $data = $this->validatePayload($request, $accountId, $account->id);
        $this->accounting->createOrUpdateAccount($account->id, $data, $accountId);

        return back()->with('success', 'Account updated.');
    }

    public function destroy(ChartOfAccount $account)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_if($account->account_id !== $accountId, 403);

        if ($account->children()->exists()) {
            return back()->withErrors(['account' => 'Cannot delete an account with child accounts.']);
        }

        if ($account->lines()->exists()) {
            return back()->withErrors(['account' => 'Cannot delete an account that has journal activity.']);
        }

        $account->delete();

        return back()->with('success', 'Account deleted.');
    }

    protected function validatePayload(Request $request, string $accountId, ?string $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('chart_of_accounts', 'code')
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->ignore($ignoreId, 'id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'])],
            'parent_id' => [
                'nullable',
                'ulid',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'description' => ['nullable', 'string'],
            'is_group' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    protected function attachChildren($account, $allAccounts)
    {
        $children = $allAccounts->where('parent_id', $account->id)->values();
        $account->children = $children->map(fn ($child) => $this->attachChildren($child, $allAccounts))->values();
        return $account;
    }
}
