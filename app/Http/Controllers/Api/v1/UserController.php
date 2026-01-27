<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class UserController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request)
    {
        $tenantId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        $query = \App\Models\User::with(['accounts', 'roles', 'permissions']);

        if ($tenantId) {
             $query->whereHas('accounts', function($q) use ($tenantId) {
                 $q->where('account_id', $tenantId); // Assuming account_user table has account_id
             });
        }

        $users = $query->paginate(15);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return \App\Http\Resources\UserResource::collection($users);
        }

        return Inertia::render('Users/Index', ['users' => $users]);
    }

    public function store(Request $request)
    {
        $tenantId = app(\AbacPermissions\Tenancy\TenantContext::class)->getAccountId();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
            'is_active' => 'boolean'
        ]);

        $user = $this->userService->createUser($tenantId, $data);

        return response()->json($user, 201);
    }

    public function show(string $userId, Request $request)
    {
        $user = $this->userService->getUser($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return new \App\Http\Resources\UserResource($user);
    }

    public function update(string $userId, Request $request)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$userId,
            'password' => 'nullable|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
            'is_active' => 'boolean'
        ]);

        $this->userService->updateUser($userId, $data);

        return response()->json(['message' => 'User updated successfully']);
    }

    public function destroy(string $userId, Request $request)
    {
        $this->userService->deleteUser($userId);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'User deleted successfully']);
        }

        return redirect()->back()->with('success', 'User deleted successfully');
    }
}
