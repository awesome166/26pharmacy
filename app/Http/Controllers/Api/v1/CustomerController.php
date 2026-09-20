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

    public function show(Request $request, string $id)
    {
        $customer = Customer::withCount('sales')->findOrFail($id);

        if ($request->wantsJson()) {
            return response()->json(['data' => $customer]);
        }

        return Inertia::render('Customers/Show', ['customer' => $customer]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customers', 'phone')->where(fn ($q) =>
                    $q->where('account_id', app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId())
                ),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $customer = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request) {
            $customer = Customer::create(array_merge($validated, [
                'id' => \Illuminate\Support\Str::ulid(),
            ]));
            app(\App\Services\DomainEventService::class)->record(
                'CUSTOMER_UPSERTED', ['customer' => $customer->attributesToArray()], $request->user()?->id,
            );
            return $customer;
        });

        if ($request->wantsJson()) {
            return response()->json(['data' => $customer->fresh()], 201);
        }

        return back()->with('success', 'Customer created successfully.');
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $validated, $request) {
            $customer->update($validated);
            app(\App\Services\DomainEventService::class)->record(
                'CUSTOMER_UPSERTED', ['customer' => $customer->fresh()->attributesToArray()], $request->user()?->id,
            );
        });

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

        \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $request) {
            $customer->delete();
            app(\App\Services\DomainEventService::class)->record(
                'CUSTOMER_DELETED', ['id' => (string) $customer->id], $request->user()?->id,
            );
        });

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Customer deleted successfully.']);
        }

        return back()->with('success', 'Customer deleted successfully.');
    }
}
