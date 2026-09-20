<?php

namespace App\Http\Middleware;

use AbacPermissions\Models\Account;
use AbacPermissions\Tenancy\TenantContext;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySyncToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && !$request->secure()) {
            return response()->json(['message' => 'Sync requires HTTPS.'], 426);
        }

        $clientId = (string) $request->header('X-Sync-Client-Id');
        $token = $request->bearerToken();

        $device = $clientId !== '' ? Device::where('device_id', $clientId)->first() : null;
        $valid = $device
            && $device->trust_status === 'active'
            && is_string($token)
            && is_string($device->sync_token_hash)
            && hash_equals($device->sync_token_hash, hash('sha256', $token));

        if (!$valid) {
            return response()->json(['message' => 'Invalid or revoked sync device credentials.'], 401);
        }

        $account = Account::find($device->account_id);
        if (!$account) {
            return response()->json(['message' => 'Sync device account no longer exists.'], 401);
        }

        app(TenantContext::class)->setAccount($account);
        $request->attributes->set('sync_device', $device);
        $device->forceFill(['last_seen_at' => now()])->save();

        return $next($request);
    }
}
