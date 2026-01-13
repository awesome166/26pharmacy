<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\DeviceHealthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class DeviceHealthController extends Controller
{
    protected $healthService;

    public function __construct(DeviceHealthService $healthService)
    {
        $this->healthService = $healthService;
    }

    /**
     * Report device heartbeat for health monitoring.
     */
    public function heartbeat(Request $request)
    {
        $this->healthService->recordHeartbeat(
            $request->header('X-Device-Id'),
            ['ip' => $request->ip(), 'agent' => $request->userAgent()]
        );

        if ($request->wantsJson()) {
            return response()->json(['status' => 'acknowledged']);
        }

        return response('', 204);
    }
}
