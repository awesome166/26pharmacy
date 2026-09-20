<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\DeviceService;
use App\Services\AuditService;
use App\Http\Requests\Device\RegisterDeviceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request)
    {
        $devices = Device::query()
            ->orderBy('created_at', 'desc')
            ->paginate((int) $request->input('per_page', 15));

        if ($request->wantsJson()) {
            return response()->json(['data' => $devices]);
        }

        return Inertia::render('Devices/Index', ['devices' => $devices]);
    }

    public function show(Request $request, string $deviceId)
    {
        $device = Device::findOrFail($deviceId);

        if ($request->wantsJson()) {
            return response()->json(['data' => $device]);
        }

        return Inertia::render('Devices/Show', ['device' => $device]);
    }

    public function update(Request $request, string $deviceId)
    {
        $device = Device::findOrFail($deviceId);

        $validated = $request->validate([
            'device_name' => 'nullable|string|max:255',
            'device_type' => 'nullable|string|max:255',
            'trust_status' => 'nullable|string|in:active,revoked,pending',
            'serial_number' => 'nullable|string|max:255',
            'mac_address' => 'nullable|string|max:255',
        ]);

        $device->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['data' => $device->fresh()]);
        }

        return redirect()->back()->with('success', 'Device updated');
    }

    public function register(RegisterDeviceRequest $request)
    {
        $accountId = (string) app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        abort_unless(\Illuminate\Support\Facades\DB::table('branches')->where('branch_id', $request->branch_id)
            ->where('account_id', $accountId)->where('is_active', true)->exists(), 422, 'Invalid branch.');
        $device = $this->deviceService->registerDevice(
            $accountId,
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
