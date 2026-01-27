<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Batch;
use Inertia\Inertia;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $query = Batch::with('drug');

        if ($request->has('drug_id')) {
            $query->where('drug_id', $request->drug_id);
        }

        if ($request->has('search')) {
            $query->where('lot_number', 'like', '%' . $request->search . '%')
                  ->orWhere('manufacturer', 'like', '%' . $request->search . '%');
        }

                              if ($request->wantsJson()) {
            return response()->json(['data' => $query->paginate($request->input('per_page', 50))]);
        }

        return Inertia::render('Batches/Index', [
            'data' => $query->paginate($request->input('per_page', 50)),
            // 'filters' => $request->only(['search', 'per_page', 'regulatory_code', 'source'])
        ]);
        // return response()->json($query->paginate($request->input('per_page', 50)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'drug_id' => 'required|string|exists:drugs,id',
            'expiry_date' => 'required|date',
            'lot_number' => 'required|string',
            'name' => 'nullable|string',
            'manufacturer' => 'required|string',
            'cost_price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);
        $batch = Batch::create(array_merge($validated, [
            'batch_id' => \Illuminate\Support\Str::ulid()
        ]));

        // Check Batch Mode Setting
        $batchMode = \App\Models\SystemSetting::getValue('inventory_batch_mode', false);

        if (!$batchMode) {
            // Direct Adding Process: Automatically add to Inventory
            // We expect extra fields or defaults for the inventory record
            $validatedInventory = $request->validate([
                'selling_price' => 'nullable|numeric',
            ]);

            // Get current account/context
            $context = app(\AbacPermissions\Tenancy\TenantContext::class);
            $accountId = $context->getAccountId();

            \App\Models\Inventory::create([
                'id' => \Illuminate\Support\Str::ulid(), // Using id as per schema
                'account_id' => $accountId,
                'drug_id' => $batch->drug_id,
                'batch_id' => $batch->id,
                'selling_price' => $request->input('selling_price', 0),
                'cost_price' => $batch->cost_price,
                'quantity_on_hand' => $batch->quantity,
                'reorder_level' =>  $request->input('reorder_level', 10), // Default
                'is_active' => true,
                'location' =>  $request->input('location', 'Store'),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json($batch, 201);
        }

        return redirect()->back()->with('success', 'Batch created');
    }
}
