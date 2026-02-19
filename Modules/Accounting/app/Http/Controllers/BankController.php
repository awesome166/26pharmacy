<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\BankAccount;

class BankController extends Controller
{
    public function index()
    {
        return BankAccount::all();
    }

    public function transactions($id)
    {
        return BankAccount::findOrFail($id)->transactions()->latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_name' => 'required',
            'account_number' => 'required',
            'bank_name' => 'required',
            'chart_of_account_id' => 'required|exists:accounting_chart_of_accounts,id',
        ]);

        return BankAccount::create($validated);
    }
}
