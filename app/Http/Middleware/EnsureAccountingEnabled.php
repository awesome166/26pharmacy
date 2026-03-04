<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountingEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        if (!$accountId) {
            abort(404, 'Accounting module is unavailable without an active account.');
        }

        $enabled = SystemSetting::getValue('accounting_enabled', false);
        if (!$enabled) {
            abort(404, 'Accounting module is disabled for this account.');
        }

        return $next($request);
    }
}
