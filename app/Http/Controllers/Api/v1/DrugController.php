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
        $branchId = $request->header('X-Branch-Id') ?? $request->user()->branch_id;

        // Dynamic Pagination Limit
        $perPage = (int) $request->input('per_page', 15);
        if (!in_array($perPage, [10, 20, 50, 100, 200])) {
            $perPage = 15;
        }

        $sales =  Drug::paginate($perPage);

        if ($request->wantsJson()) {
            return response()->json(['data' => $sales]);
        }

        return Inertia::render('Sales/Index', ['data' => $sales, 'filters' => $request->only(['search', 'per_page'])]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'strength' => 'nullable|string',
            'regulatory_code' => 'nullable|string',
        ]);
        $drug = Drug::create($validated);
        return response()->json($drug, 201);
    }
}
