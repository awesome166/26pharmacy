<?php

namespace App\Models;

use AbacPermissions\Models\Role as BaseRole;

class Role extends BaseRole
{
    /**
     * Override permissions to support eager loading.
     */
    public function permissions(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(
            \AbacPermissions\Models\Permission::class,
            'assignee',
            'assigned_permissions',
            'assignee_id',
            'permission_id'
        );
    }
}
