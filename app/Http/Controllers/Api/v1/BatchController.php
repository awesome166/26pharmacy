<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Batch;

class BatchController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'drug_id' => 'required|string|exists:drugs,drug_id',
            'expiry_date' => 'required|date',
            'lot_number' => 'required|string',
            'manufacturer' => 'required|string',
            'cost_price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);
        $batch = Batch::create($validated);
        return response()->json($batch, 201);
    }
}
