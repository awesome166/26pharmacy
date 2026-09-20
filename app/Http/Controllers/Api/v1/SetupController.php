<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;

class SetupController extends Controller
{
    public function __construct(private readonly LicenseService $licenses) {}

    public function activate(Request $request)
    {
        abort_unless(config('sync.role') === 'child', 404);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'device_fingerprint' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'ulid'],
        ]);

        $cloudUrl = rtrim((string) config('sync.cloud_url'), '/');
        if ($cloudUrl === '') {
            return response()->json(['message' => 'CLOUD_URL is not configured.'], 422);
        }
        if (app()->environment('production') && !str_starts_with(strtolower($cloudUrl), 'https://')) {
            return response()->json(['message' => 'CLOUD_URL must use HTTPS in production.'], 422);
        }

        $response = Http::timeout(30)->post("{$cloudUrl}/api/v1/activate", [
            'email' => $validated['email'],
            'password' => $validated['password'],
            'device_name' => $validated['device_name'],
            'device_fingerprint' => $validated['device_fingerprint'] ?? $validated['device_name'],
            'branch_id' => $validated['branch_id'] ?? null,
        ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Activation failed', 'details' => $response->json()], $response->status());
        }

        $data = $response->json();
        $this->licenses->verifyLicense($data['license'] ?? [], [
            'account_id' => data_get($data, 'account.id'),
            'branch_id' => data_get($data, 'branch.branch_id'),
            'device_id' => data_get($data, 'device.device_id'),
        ]);

        DB::transaction(function () use ($data) {
            $account = $data['account'];
            $account['metadata'] = isset($account['metadata']) ? json_encode($account['metadata'], JSON_THROW_ON_ERROR) : null;
            DB::table('accounts')->updateOrInsert(['id' => $account['id']], $account + [
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('branches')->updateOrInsert(['branch_id' => $data['branch']['branch_id']], $data['branch']);

            foreach ($data['users'] ?? [] as $user) {
                DB::table('users')->updateOrInsert(['id' => $user['id']], $user + [
                    'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('account_user')->updateOrInsert([
                    'user_id' => $user['id'], 'account_id' => $data['account']['id'],
                ]);
            }

            if (is_array($data['access_snapshot'] ?? null)) {
                app(\App\Services\AccessSnapshotService::class)->import($data['access_snapshot'], (string) $data['account']['id']);
            }

            DB::table('devices')->updateOrInsert(['device_id' => $data['device']['device_id']], $data['device'] + [
                'sync_token_hash' => hash('sha256', $data['sync_token']),
                'trust_status' => 'active',
                'license_checked_at' => now(),
                'last_seen_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('system_settings')->updateOrInsert(
                ['key' => 'license', 'account_id' => $data['account']['id']],
                ['value' => json_encode($data['license'], JSON_THROW_ON_ERROR), 'type' => 'tenant', 'updated_at' => now(), 'created_at' => now()]
            );
            foreach ([
                'sync_client_id' => $data['device']['device_id'],
                'sync_api_token' => Crypt::encryptString($data['sync_token']),
            ] as $key => $value) {
                DB::table('system_settings')->updateOrInsert(
                    ['key' => $key, 'account_id' => $data['account']['id']],
                    ['value' => $value, 'type' => 'tenant', 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        return response()->json([
            'message' => 'Activation successful.',
            'client_id' => $data['device']['device_id'],
            'sync_token' => $data['sync_token'],
        ]);
    }
}
