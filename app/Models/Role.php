<?php

namespace App\Models;

use AbacPermissions\Models\Role as BaseRole;

class Role extends BaseRole
{
    /**
     * Override permissions to support eager loading.
     */
    protected $with = ['assignedPermissions'];

    public function assignedPermissions()
    {
        return $this->morphMany(\App\Models\AssignedPermission::class, 'assignee');
    }


    public function getMorphClass()
    {
        return 'role';
    }


    public function permissions(): \Illuminate\Database\Eloquent\Relations\MorphToMany {
        return $this->morphToMany(
            config('abacpermissions.models.permission', \AbacPermissions\Models\Permission::class),
            'assignee',
            'assigned_permissions',
            'assignee_id',
            'permission_id'
        )->using(config('abacpermissions.models.assigned_permission', \AbacPermissions\Models\AssignedPermission::class));
    }
    public function scopeGetPermissionsWithAccess($query) {
        return $query->with(['assignedPermissions.permission']);
    }

}
