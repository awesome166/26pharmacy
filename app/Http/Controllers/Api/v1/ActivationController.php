<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActivationController extends Controller
{
    // This mocks the Cloud Side logic
    public function activate(Request $request)
    {
        // 1. Validate Admin Credentials (Mock)
        if ($request->email !== 'admin@pharmacy.com' || $request->password !== 'password') {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // 2. Generate Tenant/Branch Data (Mock from Cloud DB)
        $tenantId = Str::ulid()->toString();
        $branchId = Str::ulid()->toString();

        $tenant = [
            'tenant_id' => $tenantId,
            'legal_name' => 'My Pharmacy Ltd',
            'tax_identifier' => 'TAX-123456',
            'created_at' => now(),
            'updated_at' => now()
        ];

        $branch = [
            'branch_id' => $branchId,
            'tenant_id' => $tenantId,
            'branch_name' => 'Downtown Branch',
            'created_at' => now(),
            'updated_at' => now()
        ];

        $users = [
            [
                'id' => Str::ulid()->toString(),
                'tenant_id' => $tenantId,
                'name' => 'Admin User',
                'email' => 'admin@pharmacy.com',
                'password' => '$2y$12$Def...hashedpassword...', // Hashed 'password'
                'role_id' => Str::ulid()->toString(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // 3. Sign License (Mock Signing)
        $licensePayload = [
            'tenant_id' => $tenantId,
            'expiry_date' => now()->addYear()->toIso8601String(),
            'max_devices' => 5,
            'features' => ['pos', 'inventory', 'sync']
        ];

        // In production: openssl_sign(json_encode($licensePayload), $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $signature = base64_encode("mock_signature_for_" . $tenantId);

        return response()->json([
            'access_token' => 'mock_cloud_access_token_123',
            'license' => [
                'payload' => $licensePayload,
                'signature' => $signature
            ],
            'tenant' => $tenant,
            'branch' => $branch,
            'users' => $users
        ]);
    }
}
