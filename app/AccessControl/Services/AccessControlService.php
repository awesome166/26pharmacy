<?php



namespace App\AccessControl\Services;

use App\Models\User;
use App\AccessControl\Models\Role;
use Illuminate\Support\Facades\Cache;

class AccessControlService
{
    public static function cacheUserPermissions(User $user): void
    {
        $user->loadMissing('roles.permissions', 'permissions');

        $permissions = $user->permissions
            ->merge($user->roles->flatMap->permissions)
            ->pluck('name')
            ->unique()
            ->values();

        Cache::put("user_permissions_{$user->id}", $permissions, now()->addYear());
    }



    public static function cacheRolePermissions(Role $role): void
    {
        $role->loadMissing('permissions', 'users');

        foreach ($role->users as $user) {
            self::cacheUserPermissions($user);
        }
    }

    public static function forgetUserPermissions(User $user): void
    {
        Cache::forget("user_permissions_{$user->id}");
    }

    // public static function getCachedPermissions(User $user)
    // {
    //     return Cache::get("user_permissions_{$user->id}");
    // }

    public static function getCachedPermissions(User $user)
{
    return Cache::get("user_permissions_{$user->id}") ?? '';
}

}
