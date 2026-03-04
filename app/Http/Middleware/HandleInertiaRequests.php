<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use AbacPermissions\Facades\AbacPermissions;
use App\Models\SystemSetting;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user();
        $abac = AbacPermissions::getFrontendAuthPayload($user);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
                'accounts' => $user?->accounts,
                'current_account_id' => app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId(),
                'permissions' => $abac['permissions'] ?? [],
                'is_zeus' => (bool) ($abac['is_zeus'] ?? false),
                'is_system_zeus' => (bool) ($abac['is_system_zeus'] ?? false),
                'settings' => fn () => SystemSetting::getMergedSettings(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
