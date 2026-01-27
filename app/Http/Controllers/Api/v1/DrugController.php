<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Drug;
use Inertia\Inertia;

class DrugController extends Controller
{
    // public function index()
    // {
    //     return Drug::all();
    // }

    public function index(Request $request)
    {
        // Dynamic Pagination Limit
        $perPage = (int) $request->input('per_page', 15);
        if (!in_array($perPage, [10, 20, 50, 100, 200])) {
            $perPage = 15;
        }

        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        $query = Drug::query();

        // Source Filtering
        $source = $request->input('source', 'all');
        if ($source === 'platform') {
            $query->whereNull('account_id');
        } elseif ($source === 'store') {
            $query->where('account_id', $accountId);
        } else {
            // Default: Show both (Tenant OR Platform)
            $query->where(function ($q) use ($accountId) {
                $q->where('account_id', $accountId)
                  ->orWhereNull('account_id');
            });
        }

        // Regulatory Code
        if ($request->has('regulatory_code') && $request->regulatory_code !== 'all') {
            $query->where('regulatory_code', $request->regulatory_code);
        }

        if ($request->has('search')) {
            $searchTerm = '%' . $request->search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('strength', 'like', $searchTerm)
                  ->orWhere('regulatory_code', 'like', $searchTerm);
            });
        }

        $drugs = $query->paginate($perPage);

        if ($request->wantsJson()) {
             return response()->json(['data' => $drugs]);
        }

        return Inertia::render('Drug/Index', [
            'data' => $drugs,
            'filters' => $request->only(['search', 'per_page', 'regulatory_code', 'source'])
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'strength' => 'nullable|string',
            'regulatory_code' => 'nullable|string',
            'form' => 'nullable|string',
            'route' => 'nullable|string',
            'manufacturer' => 'nullable|string',
            'supplier' => 'nullable|string',
            'is_prescription' => 'boolean',
            'is_controlled' => 'boolean',
            'is_narcotic' => 'boolean',
            'drug_class' => 'nullable|string',
            'storage_conditions' => 'nullable|string',
            'description' => 'nullable|string',
            'side_effects' => 'nullable|string',
            'contraindications' => 'nullable|string',
            'schedule' => 'nullable|string',
            'alternate_names' => 'nullable|array',
        ]);

        $drug = Drug::create(array_merge($validated, [
            'id' => \Illuminate\Support\Str::ulid(),
            'account_id' => app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId()
        ]));

        return redirect()->back()->with('success', 'Drug created');
    }

    public function update(Request $request, string $id)
    {
        $drug = Drug::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string',
            'strength' => 'nullable|string',
            'regulatory_code' => 'nullable|string',
            'form' => 'nullable|string',
            'route' => 'nullable|string',
            'manufacturer' => 'nullable|string',
            'supplier' => 'nullable|string',

            'drug_class' => 'nullable|string',
            'storage_conditions' => 'nullable|string',
            'description' => 'nullable|string',
            'side_effects' => 'nullable|string',
            'contraindications' => 'nullable|string',
            'schedule' => 'nullable|string',
            'alternate_names' => 'nullable|array',
        ]);

        $drug->update($validated);

        return redirect()->back()->with('success', 'Drug updated');
    }

    public function destroy(string $id)
    {
        $drug = Drug::findOrFail($id);
        $drug->delete();

        return redirect()->back()->with('success', 'Drug deleted');
    }
}
