<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TaxService;
use App\Jobs\TaxRateUpdateJob;
use App\Models\TaxRate;

class TaxRateController extends Controller
{
    public function index()
    {
        return TaxRate::all();
    }

    public function store(Request $request, TaxService $taxService)
    {
        $validated = $request->validate([
            'jurisdiction' => 'required|string',
            'percentage' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
        ]);
        // Dispatch job for async update
        TaxRateUpdateJob::dispatch($validated);
        return response()->json(['message' => 'Tax rate update queued.'], 202);
    }
}
