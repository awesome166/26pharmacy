<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class EnsureContextHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Inject Tenant ID if missing and available on user
            if (!$request->headers->has('X-Tenant-Id') && !empty($user->tenant_id)) {
                $request->headers->set('X-Tenant-Id', $user->tenant_id);
            }

            // Inject Branch ID if missing and available on user
            // Note: 'branch_id' column check is safer via property access/isset
            if (!$request->headers->has('X-Branch-Id') && !empty($user->branch_id)) {
                $request->headers->set('X-Branch-Id', $user->branch_id);
            }
        }

        return $next($request);
    }
}
