<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('sync', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(30)
                ->by($request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('auth', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)
                ->by($request->ip());
        });

        \Illuminate\Support\Facades\Vite::prefetch(concurrency: 3);

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            if (method_exists($user, 'isZeus') && $user->isZeus()) {
                return true;
            }

            $permissions = $user->getAllPermissions();
            $legacyFallbacks = [
                'accounting.dashboard.view' => 'financial.view',
                'accounting.reports.view' => 'financial.view',
                'accounting.accounts.manage' => 'financial.manage',
                'accounting.journal.manage' => 'financial.manage',
                'accounting.cash.manage' => 'financial.manage',
                'accounting.close.manage' => 'financial.manage',
            ];

            // 1. Check exact match
            if (in_array($ability, $permissions)) {
                return true;
            }

            if (isset($legacyFallbacks[$ability])) {
                $legacy = $legacyFallbacks[$ability];
                if (in_array($legacy, $permissions)) {
                    return true;
                }

                foreach ($permissions as $perm) {
                    if (str_starts_with($perm, $legacy . ':')) {
                        return true;
                    }
                }
            }

            // 2. Check wildcard matches (if checking "users.manage", it passes if "users.manage:read" exists)
            foreach ($permissions as $perm) {
                if (str_starts_with($perm, $ability . ':')) {
                    return true;
                }
            }

            return null;
        });
    }
}
