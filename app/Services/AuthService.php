<?php

namespace App\Services;

/**
 * Service for handling authentication and authorization logic.
 */
class AuthService
{
    /**
     * Authenticate a user and return a token.
     *
     * @param string $email
     * @param string $password
     * @return string|null
     */
    public function authenticate(string $email, string $password)
    {
        $user = \Illuminate\Support\Facades\DB::table('users')
            ->where('email', $email)
            ->first();

        if ($user && \Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            // Simplified token return for demonstration
            return bin2hex(random_bytes(16));
        }

        return null;
    }

    /**
     * Validate user permissions for a specific action.
     *
     * @param string $userId
     * @param string $permission
     * @return bool
     */
    public function checkPermission(string $userId, string $permission)
    {
        $user = \Illuminate\Support\Facades\DB::table('users')
            ->where('user_id', $userId)
            ->first();

        if (!$user) return false;

        $role = \Illuminate\Support\Facades\DB::table('roles')
            ->where('role_id', $user->role_id)
            ->first();

        $permissions = json_decode($role->permissions ?? '[]', true);
        return in_array($permission, $permissions);
    }

    /**
     * Log out a user session.
     *
     * @param string $userId
     * @return void
     */
    public function logout(string $userId)
    {
        // In a stateless JWT/Sanctum setup, you'd invalidate the token
    }
}
