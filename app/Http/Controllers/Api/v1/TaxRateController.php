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
            'tax_type' => 'required|string|in:sales,pharmacy,special,service',
            'applicable_categories' => 'nullable|array',
            'description' => 'nullable|string',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
        ]);

        $taxRate = TaxRate::create($validated);

            if($request->wantsJson()) {
                return response()->json($taxRate, 201);
            }

        return Inertia::render('Taxes/Index', [
            'taxes' => $query->with('account')->latest()->paginate($request->input('per_page', 15)),
            'filters' => $request->only(['search', 'per_page', 'start_date', 'end_date', 'sort_by', 'sort_direction'])
        ]);

        // return redirect()->back()->with('success', 'Tax rate created successfully');


    }

    public function update(Request $request, $id)
    {
        $taxRate = TaxRate::findOrFail($id);

        $validated = $request->validate([
            'tax_name' => 'required|string|max:255',
            'jurisdiction' => 'required|string|max:255',
            'percentage' => 'required|numeric|min:0|max:100',
            'tax_type' => 'required|string|in:sales,pharmacy,special,service',
            'applicable_categories' => 'nullable|array',
            'description' => 'nullable|string',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
        ]);

        $taxRate->update($validated);

        if($request->wantsJson()) {
            return response()->json($taxRate);
        }

        return redirect()->back()->with('success', 'Tax rate updated successfully');
    }

    public function destroy(Request $request, $id)
    {
        $taxRate = TaxRate::findOrFail($id);
        $taxRate->delete();

        if($request->wantsJson()) {
            return response()->json(['message' => 'Tax rate deleted successfully']);
        }

        return redirect()->back()->with('success', 'Tax rate deleted successfully');
    }
}
