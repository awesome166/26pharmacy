<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Account;
use AbacPermissions\Models\AssignedPermission;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    /**
     * Display a listing of accounts.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = Account::withCount('users');

        // Check if user has system-level access
        $isSystemZeus = $user && $user->roles()->where('zeus_level', 'system')->exists();

        if ($user && !$isSystemZeus) {
             // Standard user: only see their own accounts
             $query->whereHas('users', function($q) use ($user) {
                 $q->where('user_id', $user->id);
             });
        }

        $accounts = $query
            ->with(['assignedPermissions.permission'])
            ->paginate(15);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($accounts);
        }

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a newly created account.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'plan'                    => 'nullable|string|in:basic,premium,enterprise',
            'metadata'                => 'nullable|array',
            'metadata.phone'          => 'nullable|string|max:50',
            'metadata.email'          => 'nullable|email|max:255',
            'metadata.logo'           => 'nullable|string|max:500',
            'metadata.business_hours' => 'nullable|string|max:500',
            'metadata.contact_email'  => 'nullable|email|max:255',
            'metadata.address'        => 'nullable|string|max:1000',
            'metadata.license'        => 'nullable|string|max:255',
            'permissions'             => 'nullable|array',
            'permissions.*.id'        => 'required|exists:permissions,id',
            'permissions.*.access'    => 'nullable|array',
        ]);

        // Separate permissions from model fields before creating
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $data['slug'] = Str::slug($data['name']);

        $account = Account::create($data);

        if (!empty($permissions)) {
            $this->syncPermissions($account, $permissions);
        }

        return response()->json($account->load('users'), 201);
    }

    /**
     * Display the specified account.
     */
    public function show($id)
    {
        $account = Account::withCount('users')
            ->with(['assignedPermissions.permission'])
            ->findOrFail($id);

        return response()->json($account);
    }

    /**
     * Update the specified account.
     */
    public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'plan'                    => 'nullable|string|in:basic,premium,enterprise',
            'metadata'                => 'nullable|array',
            'metadata.phone'          => 'nullable|string|max:50',
            'metadata.email'          => 'nullable|email|max:255',
            'metadata.logo'           => 'nullable|string|max:500',
            'metadata.business_hours' => 'nullable|string|max:500',
            'metadata.contact_email'  => 'nullable|email|max:255',
            'metadata.address'        => 'nullable|string|max:1000',
            'metadata.license'        => 'nullable|string|max:255',
            'permissions'             => 'nullable|array',
            'permissions.*.id'        => 'required|exists:permissions,id',
            'permissions.*.access'    => 'nullable|array',
        ]);

        // Separate permissions from model fields before saving
        $permissions    = $data['permissions'] ?? null;
        $hasPermissions = array_key_exists('permissions', $data);
        unset($data['permissions']);

        $account->update($data);

        // Sync permissions only if the key was present in the request
        if ($hasPermissions) {
            $this->syncPermissions($account, $permissions ?? []);
        }

        return response()->json($account->load(['users', 'assignedPermissions.permission']));
    }

    /**
     * Remove the specified account.
     */
    public function destroy($id)
    {
        $account = Account::findOrFail($id);

        // Check if account has users
        $usersCount = $account->users()->count();

        if ($usersCount > 0) {
            return response()->json([
                'message' => "Cannot delete account with {$usersCount} associated user(s). Please remove users first."
            ], 422);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    /**
     * Get users attached to an account.
     */
    public function getUsers($id)
    {
        $account = Account::findOrFail($id);
        $users = $account->users()->get();

        return response()->json($users);
    }

    /**
     * Attach a user to an account.
     */
    public function attachUser(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Check if user is already attached
        if ($account->users()->where('user_id', $data['user_id'])->exists()) {
            return response()->json([
                'message' => 'User is already attached to this account.'
            ], 422);
        }

        $account->users()->attach($data['user_id']);

        return response()->json([
            'message' => 'User attached successfully',
            'users' => $account->users()->get()
        ]);
    }

    /**
     * Detach a user from an account.
     */
    public function detachUser($accountId, $userId)
    {
        $account = Account::findOrFail($accountId);
        $user = User::findOrFail($userId);

        $account->users()->detach($userId);

        return response()->json([
            'message' => 'User detached successfully',
            'users' => $account->users()->get()
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Sync permissions for an account.
     * Accepts the PermissionSelector payload: [{ id: string, access: string[] }]
     */
    protected function syncPermissions(Account $account, array $permissions): void
    {
        // Delete all existing assigned permissions for this account
        AssignedPermission::where('assignee_type', 'account')
            ->where('assignee_id', $account->id)
            ->delete();

        if (empty($permissions)) {
            return;
        }

        $records = [];
        $now     = now();

        foreach ($permissions as $perm) {
            $permId = $perm['id'] ?? null;
            if (!$permId) continue;

            $access = isset($perm['access']) && is_array($perm['access'])
                ? json_encode($perm['access'])
                : null;

            $records[] = [
                'id'            => Str::ulid()->toString(),
                'assignee_type' => 'account',
                'assignee_id'   => $account->id,
                'permission_id' => $permId,
                'account_id'    => $account->id,   // context = the account itself
                'grantable'     => true,           // Ensure branch manager can delegate
                'access'        => $access,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        if (!empty($records)) {
            AssignedPermission::insert($records);
        }
    }
}
