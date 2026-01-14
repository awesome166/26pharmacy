<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Batch;

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

        return response()->json($query->paginate($request->input('per_page', 50)));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'drug_id' => 'required|string|exists:drugs,drug_id',
            'expiry_date' => 'required|date',
            'lot_number' => 'required|string',
            'name' => 'nullable|string',
            'manufacturer' => 'required|string',
            'cost_price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);
        $batch = Batch::create(array_merge($validated, [
            'batch_id' => \Illuminate\Support\Str::uuid()
        ]));

        if ($request->wantsJson()) {
            return response()->json($batch, 201);
        }

        return redirect()->back()->with('success', 'Batch created');
    }
}
