<?php

namespace App\Services;

/**
 * Service for managing users and their profiles.
 */
class UserService
{
    /**
     * Create a new user account.
     *
     * @param string $tenantId
     * @param array $data
     * @return \App\Models\User
     */
    public function createUser(string $tenantId, array $data)
    {
        $userData = [
            'id' => \Illuminate\Support\Str::ulid()->toString(),
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            'is_active' => $data['is_active'] ?? true,
        ];

        $user = \App\Models\User::create($userData);

        // Attach user to account
        $user->accounts()->attach($tenantId);

        // Assign Role
        if (!empty($data['role_id'])) {
            $user->roles()->attach($data['role_id']);
        }

        // Assign Direct Permissions
        if (!empty($data['permissions']) && is_array($data['permissions'])) {
            $this->syncDirectPermissions($user, $data['permissions'], $tenantId);
        }

        return $user;
    }

    /**
     * Update user information.
     *
     * @param string $userId
     * @param array $data
     * @return bool
     */
    public function updateUser(string $userId, array $data)
    {
        $user = \App\Models\User::findOrFail($userId);

        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
        if (isset($data['password']) && !empty($data['password'])) {
            $updateData['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // Sync Role
        if (isset($data['role_id'])) {
             // Sync roles (assuming single role per tenant context)
             $user->roles()->sync([$data['role_id']]);
        }

        // Sync Direct Permissions
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $tenantId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
            $this->syncDirectPermissions($user, $data['permissions'], $tenantId);
        }

        return true;
    }

    /**
     * Deactivate a user account.
     *
     * @param string $userId
     * @return bool
     */
    public function deactivateUser(string $userId)
    {
        return (bool) \App\Models\User::where('id', $userId)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    /**
     * Get user by ID.
     *
     * @param string $userId
     * @return \App\Models\User|null
     */
    public function getUser(string $userId)
    {
        return \App\Models\User::with(['roles', 'permissions'])->find($userId);
    }

    /**
     * Get all users (Paginated).
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllUsers(int $perPage = 15)
    {
        return \App\Models\User::with(['roles'])->paginate($perPage);
    }

    /**
     * Delete a user account.
     *
     * @param string $userId
     * @return bool
     */
    public function deleteUser(string $userId)
    {
        $user = \App\Models\User::findOrFail($userId);

        // Remove assignments
         \AbacPermissions\Models\AssignedPermission::where('assignee_type', 'user')
            ->where('assignee_id', $user->id)
            ->delete();

        return $user->delete();
    }

    /**
     * Sync direct permissions for a user using a diff/merge approach.
     * Accepts the PermissionSelector payload: [{ id: string, access: string[] }]
     *
     * - Permissions in the new list but not existing → INSERT
     * - Permissions in both lists with changed access → UPDATE
     * - Permissions that existed but are not in the new list → DELETE
     */
    protected function syncDirectPermissions(\App\Models\User $user, array $permissions, ?string $tenantId)
    {
        $now = now();

        // Build a map of incoming: permId => access (JSON string or null)
        $incoming = [];
        foreach ($permissions as $perm) {
            if (is_string($perm)) {
                $permId = $perm;
                $access = null;
            } else {
                $permId = $perm['id'] ?? null;
                $access = isset($perm['access']) && is_array($perm['access'])
                    ? json_encode($perm['access'])
                    : null;
            }
            if ($permId) {
                $incoming[$permId] = $access;
            }
        }

        // Fetch existing assignments for this user
        $existing = \AbacPermissions\Models\AssignedPermission::where('assignee_type', 'user')
            ->where('assignee_id', $user->id)
            ->get()
            ->keyBy('permission_id');

        $existingIds = $existing->keys()->all();
        $incomingIds = array_keys($incoming);

        // IDs to delete (were present, no longer in new list)
        $toDelete = array_diff($existingIds, $incomingIds);
        if (!empty($toDelete)) {
            \AbacPermissions\Models\AssignedPermission::where('assignee_type', 'user')
                ->where('assignee_id', $user->id)
                ->whereIn('permission_id', $toDelete)
                ->delete();
        }

        // IDs to insert (new, not previously assigned)
        $toInsert = array_diff($incomingIds, $existingIds);
        $insertRecords = [];
        foreach ($toInsert as $permId) {
            $insertRecords[] = [
                'id'            => \Illuminate\Support\Str::ulid()->toString(),
                'assignee_type' => 'user',
                'assignee_id'   => $user->id,
                'permission_id' => $permId,
                'account_id'    => $tenantId,
                'access'        => $incoming[$permId],
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }
        if (!empty($insertRecords)) {
            \AbacPermissions\Models\AssignedPermission::insert($insertRecords);
        }

        // IDs that exist in both — update access if it changed
        $toUpdate = array_intersect($existingIds, $incomingIds);
        foreach ($toUpdate as $permId) {
            $row       = $existing[$permId];
            $newAccess = $incoming[$permId];
            // Normalise existing access for comparison
            $oldAccess = is_array($row->access)
                ? json_encode($row->access)
                : $row->access;

            if ($oldAccess !== $newAccess) {
                $row->update(['access' => $newAccess, 'updated_at' => $now]);
            }
        }
    }
}
