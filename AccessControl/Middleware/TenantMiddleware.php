<?php

namespace App\AccessControl\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;
use Illuminate\Support\Facades\App;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-ID');
        $host = $request->getHost();

        $tenant = null;

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
        } else {
            // Fallback to domain lookup
            $tenant = Tenant::where('domain', $host)->first();
        }

        if (!$tenant) {
            // For local development, if no tenant found, maybe default to first one or throw error?
            // For now, let's throw 404 if strict, or maybe just proceed if it's a central route?
            // But since we want to enforce tenancy, let's be strict for API routes.
            // However, for demoschool.test, it should match.

            // If we are running locally and using localhost/127.0.0.1, we might fail.
            // Let's try to get the first Tenant as fallback for dev environment if configured.
            if (app()->environment('local')) {
                $tenant = Tenant::first();
            }
        }

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        // Set current tenant in App container
        App::instance('currentTenant', $tenant);

        // Verify that the authenticated user belongs to this tenant
        if ($request->user() && !$request->user()->belongsToTenant($tenant->id)) {
             // If user is logged in but doesn't belong to this tenant,
             // we might want to log them out or show 403.
             // For now, let's return 403 Forbidden.
             return response()->json(['message' => 'Unauthorized access to this school.'], 403);
        }

        return $next($request);
    }
}
