<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestoreAccountFromCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the header is missing but the cookie exists, inject the header.
        // This ensures the downstream DetectAbacTenant middleware works correctly.
        if (!$request->hasHeader('X-Account-ID') && $request->hasCookie('current_account_id')) {
            $request->headers->set('X-Account-ID', $request->cookie('current_account_id'));
        }

        return $next($request);
    }
}
