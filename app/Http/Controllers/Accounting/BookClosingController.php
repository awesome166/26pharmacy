<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\AccountingService;
use Illuminate\Http\Request;

class BookClosingController extends Controller
{
    public function __construct(protected AccountingService $accounting)
    {
    }

    public function close(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $payload = $request->validate([
            'business_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $closure = $this->accounting->closeBooks(
                $accountId,
                $payload['business_date'],
                $request->user()?->id,
                $payload['notes'] ?? null
            );

            if ($request->wantsJson()) {
                return response()->json(['data' => $closure]);
            }

            return back()->with('success', "Books closed for {$closure->business_date->toDateString()}.");
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->withErrors(['closing' => $e->getMessage()]);
        }
    }
}
