<?php
namespace App\AccessControl\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePermission
{
    public function handle($request, \Closure $next, $permission)
    {
        if (!$request->user() || !$request->user()->hasPermissionTo($permission)) {
            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}

