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
                    ->where('user_id', $user->id) // using uuid
                    ->orderByDesc('is_primary')
                    ->first();

                if ($tenant) {
                    $request->headers->set('X-Tenant-Id', $tenant->tenant_id);
                    // Bind to container for BelongsToTenant trait
                    \Illuminate\Support\Facades\App::instance('currentTenant', $tenant);
                }
            } else {
                 // Even if header exists, ensure it's bound if valid
                 $tId = $request->header('X-Tenant-Id');
                 $tenant = \Illuminate\Support\Facades\DB::table('tenants')->where('tenant_id', $tId)->first();
                 if ($tenant) {
                    \Illuminate\Support\Facades\App::instance('currentTenant', $tenant);
                 }
            }

            // Inject Branch ID
            if (!$request->headers->has('X-Branch-Id')) {
                // Similar logic for branch
                $branch = \Illuminate\Support\Facades\DB::table('branch_user')
                    ->where('user_id', $user->id)
                    ->first();

                if ($branch) {
                    $request->headers->set('X-Branch-Id', $branch->branch_id);
                    $actualBranch = \Illuminate\Support\Facades\DB::table('branches')->where('branch_id', $branch->branch_id)->first();
                    \Illuminate\Support\Facades\App::instance('currentBranch', $actualBranch);
                }
            } else {
                $bId = $request->header('X-Branch-Id');
                $branch = \Illuminate\Support\Facades\DB::table('branches')->where('branch_id', $bId)->first();
                if ($branch) {
                    \Illuminate\Support\Facades\App::instance('currentBranch', $branch);
                }
            }
        }

        return $next($request);
    }
}
