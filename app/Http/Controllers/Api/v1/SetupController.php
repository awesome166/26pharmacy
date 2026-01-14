<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Services\LicenseService;
use App\Services\UserService;

class SetupController extends Controller
{
    protected $licenseService;
    protected $cloudUrl;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
        $this->cloudUrl = config('services.cloud.url', env('CLOUD_URL', 'https://api.pharmacy-cloud.com'));
    }

    /**
     * Activate the local instance.
     * 1. Call Cloud to validate credentials.
     * 2. Receive License + Initial Data.
     * 3. Seed Local DB.
     */
    public function activate(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required'
        ]);

        // 1. Call Cloud Activation Endpoint
        $response = Http::post("{$this->cloudUrl}/api/v1/activate", [
            'email' => $request->email,
            'password' => $request->password,
            'device_fingerprint' => $request->device_name, // Should be hardware ID
        ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Activation failed', 'details' => $response->json()], 401);
        }

        $data = $response->json();

        // 2. Verify License Signature (Anti-Piracy Check)
        try {
            $this->licenseService->verifyLicense($data['license']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'License verification failed: ' . $e->getMessage()], 403);
        }

        // 3. Initialize Local DB Transaction
        DB::transaction(function () use ($data) {
            // Save License
            DB::table('system_settings')->updateOrInsert(
                ['key' => 'license'],
                ['value' => json_encode($data['license']), 'updated_at' => now()]
            );

            // Sync Tenant
            if (!empty($data['tenant'])) {
                DB::table('tenants')->updateOrInsert(
                    ['tenant_id' => $data['tenant']['tenant_id']],
                    $data['tenant']
                );
            }

            // Sync Branch
            if (!empty($data['branch'])) {
                DB::table('branches')->updateOrInsert(
                    ['branch_id' => $data['branch']['branch_id']],
                    $data['branch']
                );
            }

                foreach ($data['users'] as $user) {
                    DB::table('users')->updateOrInsert(
                        ['id' => $user['id']],
                        $user
                    );
                }
        });

        return response()->json(['message' => 'Activation successful. Local instance initialized.']);
    }
}
