<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TaxRate;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Inertia\Inertia;

class TaxRateController extends Controller
{
    public function index(Request $request)
    {
        $query = TaxRate::query();

        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        if ($accountId) {
            $query->where('account_id', $accountId);
        } else {
            $query->whereNull('account_id');
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('tax_name', 'like', "%{$search}%")
                  ->orWhere('jurisdiction', 'like', "%{$search}%");
            });
        }

        if ($request->has('active_only')) {
            $query->where('is_active', true);
        }


        if($request->wantsJson()) {
            return $query->with('account')->latest()->paginate($request->input('per_page', 15));
        }

        return Inertia::render('Taxes/Index', [
            'taxes' => $query->with('account')->latest()->paginate($request->input('per_page', 15)),
            'filters' => $request->only(['search', 'per_page', 'start_date', 'end_date', 'sort_by', 'sort_direction'])
        ]);

    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tax_name' => 'required|string|max:255',
            'jurisdiction' => 'required|string|max:255',
            'percentage' => 'required|numeric|min:0|max:100',
            'minimum_taxable_amount' => 'nullable|numeric|min:0',
            'maximum_taxable_amount' => 'nullable|numeric|gt:minimum_taxable_amount',
            'calculation_order' => 'nullable|integer|min:0|max:1000',
            'is_compound' => 'boolean',
            'tax_type' => 'required|string|in:sales,pharmacy,special,service',
            'applicable_categories' => 'nullable|array',
            'description' => 'nullable|string',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
        ]);
        $this->ensureNoOverlappingBracket($validated);

        $taxRate = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request) {
            $taxRate = TaxRate::create($validated);
            app(\App\Services\DomainEventService::class)->record(
                'TAX_RATE_UPSERTED', ['tax_rate' => $taxRate->attributesToArray()], $request->user()?->id,
            );
            return $taxRate;
        });

        if($request->wantsJson()) {
            return response()->json($taxRate, 201);
        }

        return redirect()->back()->with('success', 'Tax rate created successfully');
    }

    public function update(Request $request, $id)
    {
        $taxRate = TaxRate::findOrFail($id);

        $validated = $request->validate([
            'tax_name' => 'required|string|max:255',
            'jurisdiction' => 'required|string|max:255',
            'percentage' => 'required|numeric|min:0|max:100',
            'minimum_taxable_amount' => 'nullable|numeric|min:0',
            'maximum_taxable_amount' => 'nullable|numeric|gt:minimum_taxable_amount',
            'calculation_order' => 'nullable|integer|min:0|max:1000',
            'is_compound' => 'boolean',
            'tax_type' => 'required|string|in:sales,pharmacy,special,service',
            'applicable_categories' => 'nullable|array',
            'description' => 'nullable|string',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
        ]);
        $this->ensureNoOverlappingBracket($validated, (string) $taxRate->id);

        \Illuminate\Support\Facades\DB::transaction(function () use ($taxRate, $validated, $request) {
            $taxRate->update($validated);
            app(\App\Services\DomainEventService::class)->record(
                'TAX_RATE_UPSERTED', ['tax_rate' => $taxRate->fresh()->attributesToArray()], $request->user()?->id,
            );
        });

        if($request->wantsJson()) {
            return response()->json($taxRate);
        }

        return redirect()->back()->with('success', 'Tax rate updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $taxRate = TaxRate::findOrFail($id);
        \Illuminate\Support\Facades\DB::transaction(function () use ($taxRate, $request) {
            $taxRate->delete();
            app(\App\Services\DomainEventService::class)->record(
                'TAX_RATE_DELETED', ['id' => (string) $taxRate->id], $request->user()?->id,
            );
        });

        if($request->wantsJson()) {
            return response()->json(['message' => 'Tax rate deleted successfully']);
        }

        return redirect()->back()->with('success', 'Tax rate deleted successfully');
    }

    private function ensureNoOverlappingBracket(array $data, ?string $exceptId = null): void
    {
        if (!($data['is_active'] ?? true)) {
            return;
        }

        $newStart = Carbon::parse($data['effective_from'])->toDateString();
        $newEnd = !empty($data['effective_to']) ? Carbon::parse($data['effective_to'])->toDateString() : '9999-12-31';
        $newMin = (float) ($data['minimum_taxable_amount'] ?? 0);
        $newMax = isset($data['maximum_taxable_amount']) ? (float) $data['maximum_taxable_amount'] : INF;
        $overlap = TaxRate::query()
            ->where('jurisdiction', $data['jurisdiction'])
            ->where('tax_name', $data['tax_name'])
            ->where('is_active', true)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->whereDate('effective_from', '<=', $newEnd)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $newStart))
            ->get()
            ->contains(function ($rate) use ($newMin, $newMax) {
                $existingMin = (float) ($rate->minimum_taxable_amount ?? 0);
                $existingMax = $rate->maximum_taxable_amount === null ? INF : (float) $rate->maximum_taxable_amount;
                return $existingMin <= $newMax && $newMin <= $existingMax;
            });

        if ($overlap) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'minimum_taxable_amount' => 'This tax bracket overlaps an active bracket for the same tax and effective period.',
            ]);
        }
    }
}
