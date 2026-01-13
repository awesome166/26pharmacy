<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\BranchService;
use App\Http\Requests\Branch\CreateBranchRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class BranchController extends Controller
{
    protected $branchService;

    public function __construct(BranchService $branchService)
    {
        $this->branchService = $branchService;
    }

    public function store(CreateBranchRequest $request)
    {
        $branch = $this->branchService->createBranch(
            $request->tenant_id,
            $request->validated()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Branch created successfully',
                'data' => $branch
            ], 201);
        }

        return redirect()->back()->with('success', 'Branch created successfully');
    }

    public function index(string $tenantId, \Illuminate\Http\Request $request)
    {
        $branches = $this->branchService->getTenantBranches($tenantId);

        if ($request->wantsJson()) {
            return response()->json(['data' => $branches]);
        }

        return Inertia::render('Branches/Index', ['branches' => $branches]);
    }

    public function show(string $branchId, \Illuminate\Http\Request $request)
    {
        $branch = $this->branchService->getBranch($branchId);

        if (!$branch) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Branch not found'], 404);
            }
            abort(404);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $branch]);
        }

        return Inertia::render('Branches/Show', ['branch' => $branch]);
    }

    public function update(string $branchId, \Illuminate\Http\Request $request)
    {
        $this->branchService->updateBranch($branchId, $request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Branch updated successfully']);
        }

        return redirect()->back()->with('success', 'Branch updated successfully');
    }

    public function destroy(string $branchId, \Illuminate\Http\Request $request)
    {
        $this->branchService->deleteBranch($branchId);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Branch deleted successfully']);
        }

        return redirect()->back()->with('success', 'Branch deleted successfully');
    }
}
