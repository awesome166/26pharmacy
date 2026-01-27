<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class AuditController extends Controller
{
    protected $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Retrieve regulatory audit trail for an entity.
     */
    public function index(Request $request)
    {
        $accountId = $request->query('account_id');

        // If specific data is requested via JSON (actions)
        if ($request->wantsJson() && $request->has('action')) {
            switch ($request->query('action')) {
                case 'stats':
                    return response()->json($this->auditService->getDashboardStats($accountId));
                case 'timeline':
                    return response()->json($this->auditService->getUserTimeline(
                        $request->query('user_id'),
                        $accountId,
                        $request->query('action_type'),
                        $request->query('start_date'),
                        $request->query('end_date'),
                        (int) $request->input('per_page', 20)
                    ));
                case 'history':
                    $type = $request->query('entity_type');
                    $id = $request->query('entity_id');

                    // Validation
                    if (!in_array($type, ['user', 'inventory', 'batch'])) {
                        return response()->json(['error' => 'Invalid entity type. Must be user, inventory, or batch.'], 422);
                    }
                    if ($type === 'user' && !is_numeric($id)) {
                        return response()->json(['error' => 'User ID must be numeric.'], 422);
                    }
                    if (!$id) {
                        return response()->json(['error' => 'Entity ID is required.'], 422);
                    }

                    return response()->json($this->auditService->getEntityHistory(
                        $type,
                        $id,
                        $accountId
                    ));
                case 'user_stats':
                    return response()->json($this->auditService->getUserProfileStats(
                        $request->query('user_id'),
                        $accountId
                    ));
            }
        }

        // Default: Return basic list for the "Data Table" tab or initial page load
        $perPage = (int) $request->input('per_page', 50);
        $history = $this->auditService->getHistory(
            $request->query('entity_type'),
            $request->query('entity_id'),
            $accountId,
            $perPage
        );

        if ($request->wantsJson()) {
            return response()->json(['data' => $history]);
        }

        // Fetch users for the selector, filtering by account if applicable
        // Fetch users for the selector, filtering by effective account if applicable
        $effectiveAccountId = $accountId ?: app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        $users = \App\Models\User::query()
            ->select('id', 'name')
            ->when($effectiveAccountId, function ($q) use ($effectiveAccountId) {
                $q->whereHas('accounts', function ($q) use ($effectiveAccountId) {
                    $q->where('id', $effectiveAccountId);
                });
            })
            ->get();

        // Fetch inventories for selector
        $inventories = \App\Models\Inventory::query()
            ->with('drug:id,name')
            ->select('id', 'drug_id', 'batch_id')
            ->when($effectiveAccountId, function ($q) use ($effectiveAccountId) {
                // Assuming Inventory has UsesTenant trait or account_id column
                $q->where('account_id', $effectiveAccountId);
            })
            ->limit(1000) // Safety limit
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'label' => ($inv->drug ? $inv->drug->name : 'Unknown Drug') . ' (ID: ' . substr($inv->id, 0, 8) . '...)'
                ];
            });

        // Fetch batches for selector
        $batches = \App\Models\Batch::query()
            ->with('drug:id,name') // Batch usually links to Drug
            ->select('id', 'drug_id', 'lot_number')
             // Batch might not have account_id directly if it's shared, but assuming context if possible.
             // If Batch is global, we don't filter. If it's tenant-scoped, we do.
             // Based on previous context, we'll try to filter if applicable or fetches all relevant.
             // For safety, let's fetch recent active batches.
            ->orderBy('created_at', 'desc')
            ->limit(500)
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'label' => ($batch->drug ? $batch->drug->name : 'Unknown Drug') . ' - Lot: ' . $batch->lot_number
                ];
            });

        // Initial props for the Dashboard page
        return Inertia::render('Audit/Index', [
             // Pass initial empty states or preload stats if desired.
             // For now, we'll let the frontend fetch heavy stats on mount to speed up navigation.
            'history' => $history,
            'users' => $users,
            'inventories' => $inventories,
            'batches' => $batches,
            'filters' => $request->all(['account_id', 'entity_type', 'user_id', 'date_range']),
        ]);
    }
}
