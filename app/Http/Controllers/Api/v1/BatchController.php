<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Batch;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory;
use App\Services\StoreInventoryService;

class BatchController extends Controller
{
    public function index(Request $request)
    {
        $query = Batch::with('drug');

        if ($request->has('drug_id')) {
            $query->where('drug_id', $request->drug_id);
        }

        if ($request->has('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('lot_number', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%')
                    ->orWhere('supplier', 'like', '%' . $search . '%')
                    ->orWhere('manufacturer', 'like', '%' . $search . '%');
            });
        }

        $data = $query->paginate($request->input('per_page', 50));

        if ($request->wantsJson()) {
            return response()->json(['data' => $data]);
        }

        return Inertia::render('Batches/Index', [
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'drug_id' => 'required|ulid|exists:drugs,id',
            'expiry_date' => 'required|date',
            'lot_number' => 'required|string|max:255',
            'name' => 'nullable|string',
            'supplier' => 'required|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'location' => 'nullable|string|max:255',
        ]);
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_if(!$accountId, 422, 'Select an account before creating batches.');

        $batch = DB::transaction(function () use ($validated, $request, $accountId) {
            $batch = Batch::create([
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'drug_id' => $validated['drug_id'],
                'expiry_date' => $validated['expiry_date'],
                'lot_number' => $validated['lot_number'],
                'name' => $validated['name'] ?? null,
                'supplier' => $validated['supplier'],
                'manufacturer' => $validated['manufacturer'] ?? $validated['supplier'],
                'cost_price' => $validated['cost_price'],
                'quantity' => $validated['quantity'],
                'quantity_recieved' => $validated['quantity'],
                'storage_location' => $validated['location'] ?? null,
                'is_active' => true,
            ]);

            // Check Batch Mode Setting
            $batchMode = \App\Models\SystemSetting::getValue('inventory_batch_mode', false);
            if (!$batchMode) {
                $location = $validated['location'] ?? 'Shelf A';
                $sellingPrice = $validated['selling_price'] ?? 0;

                $existing = Inventory::query()
                    ->where('account_id', $accountId)
                    ->where('drug_id', $batch->drug_id)
                    ->where('batch_id', $batch->id)
                    ->where('location', $location)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'quantity_on_hand' => (int) $existing->quantity_on_hand + (int) $batch->quantity,
                        'selling_price' => $sellingPrice,
                        'cost_price' => $batch->cost_price,
                        'is_active' => true,
                    ]);
                } else {
                    Inventory::create([
                        'id' => (string) \Illuminate\Support\Str::ulid(),
                        'account_id' => $accountId,
                        'drug_id' => $batch->drug_id,
                        'batch_id' => $batch->id,
                        'selling_price' => $sellingPrice,
                        'cost_price' => $batch->cost_price,
                        'quantity_on_hand' => $batch->quantity,
                        'reorder_level' =>  $request->input('reorder_level', 10),
                        'is_active' => true,
                        'location' =>  $location,
                    ]);
                }
            }

            return $batch;
        });

        app(StoreInventoryService::class)->invalidateCache($accountId);

        if ($request->wantsJson()) {
            return response()->json($batch, 201);
        }

        return redirect()->back()->with('success', 'Batch created');
    }

    public function update(Request $request, Batch $batch)
    {
        $validated = $request->validate([
            'drug_id' => 'required|ulid|exists:drugs,id',
            'expiry_date' => 'required|date',
            'lot_number' => 'required|string|max:255',
            'name' => 'nullable|string',
            'supplier' => 'required|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'cost_price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'location' => 'nullable|string|max:255',
        ]);

        $batch->update([
            'drug_id' => $validated['drug_id'],
            'expiry_date' => $validated['expiry_date'],
            'lot_number' => $validated['lot_number'],
            'name' => $validated['name'] ?? null,
            'supplier' => $validated['supplier'],
            'manufacturer' => $validated['manufacturer'] ?? $validated['supplier'],
            'cost_price' => $validated['cost_price'],
            'quantity' => $validated['quantity'],
            'storage_location' => $validated['location'] ?? $batch->storage_location,
        ]);

        app(StoreInventoryService::class)->invalidateCache((string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId());

        if ($request->wantsJson()) {
            return response()->json(['data' => $batch->fresh('drug')]);
        }

        return back()->with('success', 'Batch updated');
    }

    public function destroy(Batch $batch)
    {
        if ($batch->inventories()->where('quantity_on_hand', '>', 0)->exists()) {
            return response()->json([
                'message' => 'Cannot delete batch with active inventory quantity.',
            ], 422);
        }

        $batch->delete();

        app(StoreInventoryService::class)->invalidateCache((string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId());

        return response()->json(['message' => 'Batch deleted']);
    }
}
