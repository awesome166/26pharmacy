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
        $history = $this->auditService->getHistory(
            $request->query('entity_type'),
            $request->query('entity_id')
        );

        if ($request->wantsJson()) {
            return response()->json(['data' => $history]);
        }

        return Inertia::render('Audit/Index', ['history' => $history]);
    }
}
