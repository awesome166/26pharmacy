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
     * @return object
     */
    public function createUser(string $tenantId, array $data)
    {
        $id = \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('users')->insert([
            'user_id' => $id,
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['user_id' => $id];
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
        if (isset($data['password'])) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
        }

        return (bool) \Illuminate\Support\Facades\DB::table('users')
            ->where('user_id', $userId)
            ->update(array_merge($data, ['updated_at' => now()]));
    }

    /**
     * Deactivate a user account.
     *
     * @param string $userId
     * @return bool
     */
    public function deactivateUser(string $userId)
    {
        return (bool) \Illuminate\Support\Facades\DB::table('users')
            ->where('user_id', $userId)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    /**
     * Get user by ID.
     *
     * @param string $userId
     * @return object|null
     */
    public function getUser(string $userId)
    {
        return \Illuminate\Support\Facades\DB::table('users')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get all users (Paginated).
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllUsers(int $perPage = 15)
    {
        return \Illuminate\Support\Facades\DB::table('users')->paginate($perPage);
    }

    /**
     * Delete a user account.
     *
     * @param string $userId
     * @return bool
     */
    public function deleteUser(string $userId)
    {
        return (bool) \Illuminate\Support\Facades\DB::table('users')
            ->where('user_id', $userId)
            ->delete();
    }
}
