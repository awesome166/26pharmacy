<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function authenticate(string $email, string $password): ?string
    {
        $user = User::where('email', $email)->first();

        if (!$user || !$user->is_active || !Hash::check($password, $user->password)) {
            return null;
        }

        $accountId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();
        if ($accountId && !$user->accounts()->where('accounts.id', $accountId)->exists()) {
            return null;
        }

        return $user->createToken('api-token')->plainTextToken;
    }

    public function checkPermission(string $userId, string $permission): bool
    {
        $user = User::find($userId);

        if (!$user) return false;

        return $user->can($permission);
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
