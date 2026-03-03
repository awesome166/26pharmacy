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


        \Illuminate\Support\Facades\Vite::prefetch(concurrency: 3);

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            $permissions = $user->getAllPermissions();

            // 1. Check exact match
            if (in_array($ability, $permissions)) {
                return true;
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
