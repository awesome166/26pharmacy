<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireSyncRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        abort_unless(in_array($role, ['parent', 'child'], true), 404);
        abort_unless(config('sync.role') === $role, 404);

        return $next($request);
    }
}
