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

        $query = Drug::query();

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('strength', 'like', '%' . $request->search . '%')
                  ->orWhere('regulatory_code', 'like', '%' . $request->search . '%');
        }

        $drugs = $query->paginate($perPage);

        if ($request->wantsJson()) {
            return response()->json(['data' => $drugs]);
        }

        return Inertia::render('Drug/Index', [
            'data' => $drugs,
            'filters' => $request->only(['search', 'per_page'])
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'strength' => 'nullable|string',
            'regulatory_code' => 'nullable|string',
        ]);

        $drug = Drug::create(array_merge($validated, [
            'drug_id' => \Illuminate\Support\Str::uuid()
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
