<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashMovementController extends Controller
{
    public function __construct(protected AccountingService $accounting)
    {
    }

    public function store(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        $payload = $request->validate([
            'movement' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'cash_account_id' => ['nullable', 'ulid', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('account_id', $accountId))],
            'counterpart_account_id' => ['required', 'ulid', Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('account_id', $accountId))],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->accounting->createCashMovement($payload, $accountId, $request->user()?->id);

            if ($request->wantsJson()) {
                return response()->json(['data' => $entry], 201);
            }

            return back()->with('success', 'Cash movement posted.');
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['cash' => $e->getMessage()]);
        }
    }
}
