<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\AccountingService;

use Inertia\Inertia;

class JournalEntryController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index()
    {
        $entries = JournalEntry::with('details.chartOfAccount')->latest()->paginate(10);

        if (request()->wantsJson()) {
            return $entries;
        }

        return Inertia::render('Accounting/JournalEntries/Index', [
            'entries' => $entries
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'required',
            'details' => 'required|array|min:2',
            'details.*.chart_of_account_id' => 'required|exists:accounting_chart_of_accounts,id',
            'details.*.debit' => 'required|numeric',
            'details.*.credit' => 'required|numeric',
        ]);

        // Verify balance
        $totalDebit = collect($validated['details'])->sum('debit');
        $totalCredit = collect($validated['details'])->sum('credit');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            return response()->json(['error' => 'Journal Entry is not balanced'], 422);
        }

        // Create Entry logic here (simplified for controller)
        // In real app, this would be in Service or use DB transaction block

        $entry = JournalEntry::create([
            'entry_number' => 'JE-' . time(), // auto-gen logic needed
            'date' => $validated['date'],
            'description' => $validated['description'],
            'status' => 'draft',
        ]);

        foreach ($validated['details'] as $detail) {
            $entry->details()->create($detail);
        }

        return $entry->load('details');
    }

    public function show($id)
    {
        $entry = JournalEntry::with('details.chartOfAccount')->findOrFail($id);

        if (request()->wantsJson()) {
            return $entry;
        }

        return Inertia::render('Accounting/JournalEntries/Show', [
            'entry' => $entry
        ]);
    }

    public function post($id)
    {
        $entry = JournalEntry::findOrFail($id);
        try {
            $this->accountingService->postEntry($entry);
            return $entry;
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function void($id)
    {
        $entry = JournalEntry::findOrFail($id);
        try {
            $this->accountingService->voidEntry($entry);
            return $entry;
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
