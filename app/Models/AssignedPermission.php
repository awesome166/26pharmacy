<?php

namespace App\Models;

use AbacPermissions\Models\AssignedPermission as BaseAssignedPermission;

class AssignedPermission extends BaseAssignedPermission
{
    protected $casts = [
        'access' => 'array',
    ];
}
