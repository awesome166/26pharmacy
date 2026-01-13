<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\DeviceService;
use App\Services\AuditService;
use App\Http\Requests\Device\RegisterDeviceRequest;
use Illuminate\Http\JsonResponse;
use App\Jobs\AuditEventJob;
use Inertia\Inertia;

class DeviceController extends Controller
{
    protected $deviceService;
    protected $auditService;

    public function __construct(DeviceService $deviceService, AuditService $auditService)
    {
        $this->deviceService = $deviceService;
        $this->auditService = $auditService;
    }

    public function register(RegisterDeviceRequest $request)
    {
        $device = $this->deviceService->registerDevice(
            $request->branch_id,
            $request->validated()
        );

        // Regulatory Audit
        AuditEventJob::dispatch([
            'entity_type' => 'device',
            'entity_id' => $device->device_id,
            'action' => 'DEVICE_REGISTERED',
            'metadata' => ['request_ip' => $request->ip()]
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Device registered successfully',
                'data' => $device
            ], 201);
        }

        return redirect()->back()->with('success', 'Device registered successfully');
    }

    public function revoke(string $deviceId, \Illuminate\Http\Request $request)
    {
        $this->deviceService->revokeDevice($deviceId);

        AuditEventJob::dispatch([
            'entity_type' => 'device',
            'entity_id' => $deviceId,
            'action' => 'DEVICE_REVOKED'
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Device trust revoked']);
        }

        return redirect()->back()->with('success', 'Device trust revoked');
    }
}
