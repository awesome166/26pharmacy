<?php

namespace App\AccessControl\Traits;

use App\AccessControl\Models\Permission;
use Illuminate\Support\Facades\Cache;

trait HasPermissions
{
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function roles()
    {
        return $this->belongsToMany(\App\AccessControl\Models\Role::class, 'user_roles');
    }

    public function getAllPermissionsAttribute()
    {
        return Cache::rememberForever("user_permissions_{$this->id}", function () {
            $rolePermissions = $this->roles()->with('permissions')->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('name')
                ->unique();

            $directPermissions = $this->permissions()->pluck('name');

            return $rolePermissions->merge($directPermissions)->unique()->values();
        });
    }

    public function hasPermission(string $permission): bool
    {
        return $this->all_permissions->contains($permission);
    }

    public function clearCachedPermissions(): void
    {
        Cache::forget("user_permissions_{$this->id}");
    }
}