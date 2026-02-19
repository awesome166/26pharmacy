<?php

namespace App\Models;

use AbacPermissions\Models\Account as BaseAccount;

class Account extends BaseAccount
{
    /**
     * Direct assigned permissions for this account entity.
     * Stored with assignee_type = 'account'.
     */
    public function assignedPermissions()
    {
        return $this->morphMany(AssignedPermission::class, 'assignee');
    }

    public function getMorphClass()
    {
        return 'account';
    }

}
