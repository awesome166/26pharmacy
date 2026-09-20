<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['data' => Branch::orderBy('name')->paginate($request->integer('per_page', 25))]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['account_id'] = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        $branch = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request) {
            $branch = Branch::create($data);
            app(\App\Services\DomainEventService::class)->record(
                'BRANCH_UPSERTED', ['branch' => $branch->attributesToArray()], $request->user()?->id, $branch->branch_id,
            );
            return $branch;
        });
        return response()->json(['data' => $branch], 201);
    }

    public function show(Branch $branch)
    {
        return response()->json(['data' => $branch]);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $this->validated($request, true);
        \Illuminate\Support\Facades\DB::transaction(function () use ($branch, $data, $request) {
            $branch->update($data);
            app(\App\Services\DomainEventService::class)->record(
                'BRANCH_UPSERTED', ['branch' => $branch->fresh()->attributesToArray()], $request->user()?->id, $branch->branch_id,
            );
        });
        return response()->json(['data' => $branch->fresh()]);
    }

    public function destroy(Request $request, Branch $branch)
    {
        abort_if(\App\Models\Device::where('branch_id', $branch->branch_id)->where('trust_status', 'active')->exists(), 422, 'Revoke active devices before deactivating this branch.');
        \Illuminate\Support\Facades\DB::transaction(function () use ($branch, $request) {
            $branch->update(['is_active' => false]);
            app(\App\Services\DomainEventService::class)->record(
                'BRANCH_UPSERTED', ['branch' => $branch->fresh()->attributesToArray()], $request->user()?->id, $branch->branch_id,
            );
        });
        return response()->json(['message' => 'Branch deactivated.']);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'name' => [$prefix, 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'tax_jurisdiction' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'timezone'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
