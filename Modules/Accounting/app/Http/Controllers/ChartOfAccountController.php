<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\ChartOfAccount;

use Inertia\Inertia;

class ChartOfAccountController extends Controller
{
    public function index()
    {
        $accounts = ChartOfAccount::with('children')->whereNull('parent_id')->get();

        if (request()->wantsJson()) {
            return $accounts;
        }

        return Inertia::render('Accounting/Accounts/Index', [
            'accounts' => $accounts
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:accounting_chart_of_accounts,code',
            'name' => 'required',
            'type' => 'required|in:Asset,Liability,Equity,Revenue,Expense',
            'parent_id' => 'nullable|exists:accounting_chart_of_accounts,id',
            'is_group' => 'boolean',
        ]);

        return ChartOfAccount::create($validated);
    }

    public function show($id)
    {
        return ChartOfAccount::with('children')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $account = ChartOfAccount::findOrFail($id);
        $account->update($request->all());
        return $account;
    }

    public function destroy($id)
    {
        ChartOfAccount::destroy($id);
        return response()->noContent();
    }
}
