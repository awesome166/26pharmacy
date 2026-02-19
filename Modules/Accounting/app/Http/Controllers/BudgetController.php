<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\Budget;

class BudgetController extends Controller
{
    public function index()
    {
        return Budget::with('details')->latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fiscal_year' => 'required',
            'name' => 'required',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        return Budget::create($validated);
    }

    public function addDetail(Request $request, $id)
    {
        $budget = Budget::findOrFail($id);

        $validated = $request->validate([
            'cost_center_id' => 'required|exists:accounting_cost_centers,id',
            'chart_of_account_id' => 'required|exists:accounting_chart_of_accounts,id',
            'amount' => 'required|numeric',
            'period' => 'required',
        ]);

        return $budget->details()->create($validated);
    }
}
