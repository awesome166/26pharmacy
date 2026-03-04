<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()->withCount('sales');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()->paginate((int) $request->input('per_page', 15));

        if ($request->wantsJson()) {
            return response()->json(['data' => $customers]);
        }

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'per_page']),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')->where(function ($q) use ($customer) {
                    return $q->where('account_id', $customer->account_id);
                })->ignore($customer->id, 'id'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $customer->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['data' => $customer->fresh()->loadCount('sales')]);
        }

        return back()->with('success', 'Customer updated successfully.');
    }

    public function destroy(Request $request, Customer $customer)
    {
        if ($customer->sales()->exists()) {
            $message = 'Customer cannot be deleted because they have purchase history.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }
            return back()->withErrors(['customer' => $message]);
        }

        $customer->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Customer deleted successfully.']);
        }

        return back()->with('success', 'Customer deleted successfully.');
    }
}
