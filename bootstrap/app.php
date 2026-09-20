<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'current_account_id']);

        $middleware->web(append: [
            HandleAppearance::class,
            \App\Http\Middleware\RestoreAccountFromCookie::class, // Bridge persistence
            \AbacPermissions\Http\Middleware\DetectAbacTenant::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // \AbacPermissions\Http\Middleware\AppendPermissions::class,

        ]);

        $middleware->api(append: [
            \App\Http\Middleware\RestoreAccountFromCookie::class, // Bridge persistence
            \AbacPermissions\Http\Middleware\DetectAbacTenant::class,
            // \AbacPermissions\Http\Middleware\AppendPermissions::class,


        ]);

        $middleware->alias([
            // 'permission' => \App\AccessControl\Middleware\EnsurePermission::class,
            'accounting.enabled' => \App\Http\Middleware\EnsureAccountingEnabled::class,
            'sync.token' => \App\Http\Middleware\VerifySyncToken::class,
            'sync.role' => \App\Http\Middleware\RequireSyncRole::class,
            'licensed' => \App\Http\Middleware\EnsureLicensed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
