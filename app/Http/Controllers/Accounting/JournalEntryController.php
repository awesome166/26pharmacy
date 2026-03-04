<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class JournalEntryController extends Controller
{
    public function __construct(protected AccountingService $accounting)
    {
    }

    public function index(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $status = (string) $request->input('status', 'all');

        $entries = $this->accounting->getEntriesForIndex($accountId, $status, (int) $request->input('per_page', 20));
        $accounts = $this->accounting->getAccountsWithBalances($accountId)->where('is_group', false)->values();

        if ($request->wantsJson()) {
            return response()->json($entries);
        }

        return Inertia::render('Accounting/JournalEntries/Index', [
            'entries' => $entries,
            'accounts' => $accounts,
            'filters' => $request->only(['status', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $payload = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'details' => ['required', 'array', 'min:2'],
            'details.*.chart_of_account_id' => [
                'required',
                'ulid',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('account_id', $accountId)),
            ],
            'details.*.debit' => ['nullable', 'numeric', 'min:0'],
            'details.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $entry = $this->accounting->createJournalEntry($payload, $accountId);
            return redirect()->back()->with('success', "Journal entry {$entry->entry_number} created.");
        } catch (\Throwable $e) {
            return back()->withErrors(['entry' => $e->getMessage()]);
        }
    }

    public function post(Request $request, JournalEntry $entry)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_if($entry->account_id !== $accountId, 403);

        try {
            $this->accounting->postEntry($entry->id, $accountId, $request->user()?->id);
            return back()->with('success', 'Entry posted.');
        } catch (\Throwable $e) {
            return back()->withErrors(['entry' => $e->getMessage()]);
        }
    }

    public function void(Request $request, JournalEntry $entry)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_if($entry->account_id !== $accountId, 403);

        try {
            $this->accounting->voidEntry($entry->id, $accountId, $request->user()?->id);
            return back()->with('success', 'Entry voided.');
        } catch (\Throwable $e) {
            return back()->withErrors(['entry' => $e->getMessage()]);
        }
    }
}
