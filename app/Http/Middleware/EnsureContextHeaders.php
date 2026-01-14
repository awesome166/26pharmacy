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
            // Inject Tenant ID
            if (!$request->headers->has('X-Tenant-Id')) {
                // Determine tenant from pivot. Prioritize primary.
                $tenant = \Illuminate\Support\Facades\DB::table('tenant_user')
                    ->where('user_id', $user->user_id) // using uuid
                    ->orderByDesc('is_primary')
                    ->first();

                if ($tenant) {
                    $request->headers->set('X-Tenant-Id', $tenant->tenant_id);
                }
            }

            // Inject Branch ID
            if (!$request->headers->has('X-Branch-Id')) {
                // Similar logic for branch
                $branch = \Illuminate\Support\Facades\DB::table('branch_user')
                    ->where('user_id', $user->user_id)
                    ->first();

                if ($branch) {
                    $request->headers->set('X-Branch-Id', $branch->branch_id);
                }
            }
        }

        return $next($request);
    }
}
