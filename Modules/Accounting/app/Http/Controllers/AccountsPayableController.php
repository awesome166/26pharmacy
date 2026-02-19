<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Models\AccountsPayable;
use Modules\Accounting\Models\Payment;

class AccountsPayableController extends Controller
{
    public function index()
    {
        return AccountsPayable::with('payments')->latest()->paginate(10);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_number' => 'required',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date',
            'amount' => 'required|numeric',
            'description' => 'required',
        ]);

        return AccountsPayable::create($validated);
    }

    public function addPayment(Request $request, $id)
    {
        $payable = AccountsPayable::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|max:' . $payable->remaining_amount,
            'payment_method' => 'required',
            'payment_date' => 'required|date',
        ]);

        $payment = $payable->payments()->create($validated);

        // Update payable status/paid amount
        $payable->amount_paid += $payment->amount;
        $payable->save();
        // Logic to update status (partial/paid) would go here

        return $payment;
    }
}
