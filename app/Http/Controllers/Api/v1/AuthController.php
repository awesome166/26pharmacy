<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        $token = $this->authService->authenticate(
            $request->email,
            $request->password
        );

        if (!$token) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
            return redirect()->back()->withErrors(['email' => 'Invalid credentials']);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Login successful',
                'token' => $token
            ]);
        }

        return redirect()->intended('/dashboard');
    }

    public function logout(\Illuminate\Http\Request $request)
    {
        // Logout logic handled via service
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged out successfully']);
        }

        return redirect('/login');
    }
}
