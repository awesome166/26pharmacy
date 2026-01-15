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
        $users = \App\Models\User::with(['accounts', 'permissions'])->paginate(15);

        if ($request->wantsJson()) {
            return \App\Http\Resources\UserResource::collection($users);
        }

        return Inertia::render('Users/Index', ['users' => $users]);
    }

    public function store(Request $request)
    {
        // For brevity, using simple validation here; usually should use a FormRequest
        $data = $request->validate([
            'account_id' => 'required|uuid',
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_id' => 'required|uuid'
        ]);

        $user = $this->userService->createUser($data['account_id'], $data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $user], 201);
        }

        return redirect()->back()->with('success', 'User created successfully');
    }

    public function show(string $userId, Request $request)
    {
        $user = \App\Models\User::with(['accounts', 'permissions'])->find($userId);

        if (!$user) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'User not found'], 404);
            }
            abort(404);
        }

        if ($request->wantsJson()) {
            return new \App\Http\Resources\UserResource($user);
        }

        return Inertia::render('Users/Show', ['user' => $user]);
    }

    public function update(string $userId, Request $request)
    {
        $this->userService->updateUser($userId, $request->all());

        if ($request->wantsJson()) {
            return response()->json(['message' => 'User updated successfully']);
        }

        return redirect()->back()->with('success', 'User updated successfully');
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
