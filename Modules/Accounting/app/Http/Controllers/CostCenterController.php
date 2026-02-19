<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\CostCenter;

class CostCenterController extends Controller
{
    public function index()
    {
        return CostCenter::with('children')->whereNull('parent_id')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|unique:accounting_cost_centers,code',
            'name' => 'required',
            'type' => 'required',
        ]);

        return CostCenter::create($validated);
    }
}
