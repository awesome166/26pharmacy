<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use App\Http\Requests\Tenant\CreateTenantRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TenantController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $tenants = $this->tenantService->getAllTenants();

        if ($request->wantsJson()) {
            return response()->json(['data' => $tenants]);
        }

        return Inertia::render('Tenants/Index', ['tenants' => $tenants]);
    }

    public function store(CreateTenantRequest $request)
    {
        $tenant = $this->tenantService->createTenant($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Tenant created successfully',
                'data' => $tenant
            ], 201);
        }

        return redirect()->back()->with('success', 'Tenant created successfully');
    }

    public function show(string $tenantId, \Illuminate\Http\Request $request)
    {
        $tenant = $this->tenantService->getTenant($tenantId);

        if (!$tenant) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Tenant not found'], 404);
            }
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $tenant]);
        }

        return Inertia::render('Tenants/Show', ['tenant' => $tenant]);
    }

    public function update(string $tenantId, \Illuminate\Http\Request $request)
    {
        $this->tenantService->updateTenant($tenantId, $request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tenant updated successfully']);
        }

        return redirect()->back()->with('success', 'Tenant updated successfully');
    }

    public function destroy(string $tenantId, \Illuminate\Http\Request $request)
    {
        $this->tenantService->deleteTenant($tenantId);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tenant deleted successfully']);
        }

        return redirect()->route('tenants.index')->with('success', 'Tenant deleted successfully');
    }
}
