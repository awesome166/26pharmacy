<?php

namespace App\Services;

use AbacPermissions\Facades\AbacPermissions;

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
            AbacPermissions::assignRole($user, $data['role_id']);
        }

        // Assign Direct Permissions
        if (!empty($data['permissions']) && is_array($data['permissions'])) {
            AbacPermissions::syncUserPermissions(
                $user->id,
                $this->normalizePermissionsPayload($data['permissions']),
                $tenantId
            );
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
             AbacPermissions::syncRoles($user, [$data['role_id']]);
        }

        // Sync Direct Permissions
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            $tenantId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
            AbacPermissions::syncUserPermissions(
                $user->id,
                $this->normalizePermissionsPayload($data['permissions']),
                $tenantId
            );
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

    protected function normalizePermissionsPayload(array $permissions): array
    {
        $normalized = [];

        foreach ($permissions as $perm) {
            if (is_string($perm)) {
                $normalized[] = ['id' => $perm, 'access' => null];
                continue;
            }

            $permId = $perm['id'] ?? null;
            if (!$permId) {
                continue;
            }

            $normalized[] = [
                'id' => $permId,
                'access' => isset($perm['access']) && is_array($perm['access']) ? $perm['access'] : null,
                'grantable' => isset($perm['grantable']) ? (bool) $perm['grantable'] : false,
            ];
        }

        return $normalized;
    }
}
