<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use AbacPermissions\Models\Role;
use App\Models\Role;
use AbacPermissions\Models\Permission;
use AbacPermissions\Models\AssignedPermission;
use AbacPermissions\Tenancy\TenantContext;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Get current tenant
        $tenantId = app(TenantContext::class)->getAccountId();

        $query = Role::query();

        if ($tenantId) {
            $query->where('account_id', $tenantId);
        } else {
            // If no tenant, maybe show global roles or empty?
            // Assuming we only show tenant roles for now.
             $query->whereNull('account_id');
        }

        $roles = $query->with('permissions')->paginate(15);

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($roles);
        }

        return \Inertia\Inertia::render('Roles/Index', ['roles' => $roles]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $tenantId = app(TenantContext::class)->getAccountId();

        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'account_id' => $tenantId, // Null if system (but usually via tenant context)
            'guard_name' => 'web',
        ]);

        if ($request->has('permissions')) {
            $this->syncPermissions($role, $request->permissions, $tenantId);
        }

        return response()->json($role->load('permissions'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $tenantId = app(TenantContext::class)->getAccountId();

        $role = Role::where(function($q) use ($tenantId) {
            $q->where('account_id', $tenantId)
              ->orWhereNull('account_id'); // Allow viewing system roles? Maybe.
        })->findOrFail($id);

        return response()->json($role->load('permissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $tenantId = app(TenantContext::class)->getAccountId();

        $role = Role::where('id', $id)->where('account_id', $tenantId)->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $request->name,
        ]);

        if ($request->has('permissions')) {
            $this->syncPermissions($role, $request->permissions, $tenantId);
        }

        return response()->json($role->load('permissions'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $tenantId = app(TenantContext::class)->getAccountId();

        $role = Role::where('id', $id)->where('account_id', $tenantId)->firstOrFail();

        // Remove assigned permissions
        AssignedPermission::where('assignee_type', 'role')
            ->where('assignee_id', $role->id)
            ->delete();

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }

    /**
     * Custom sync permissions logic because we are managing AssignedPermission directly
     */
    protected function syncPermissions(Role $role, array $permissionIds, ?string $tenantId)
    {
        // 1. Delete existing assignments for this role
        // Note: We are nuking connection. In a real ABAC system we might want to preserve some specific overrides.
        // For simplicity of this task "CRUD Role", we sync (replace).
        AssignedPermission::where('assignee_type', 'role')
            ->where('assignee_id', $role->id)
            ->delete();

        // 2. Create new assignments
        $records = [];
        $now = now();
        foreach ($permissionIds as $permId) {
            $records[] = [
                'assignee_type' => 'role',
                'assignee_id' => $role->id,
                'permission_id' => $permId,
                'account_id' => null, // Permissions on a role are universal usually?
                                      // Or if the role is tenant-scoped, the permission assignment implicitly follows.
                                      // If we assign a permission to a role, do we scope the assignment to the account?
                                      // Based on AssignedPermission::scopeForAccount, it filters by account_id.
                                      // However, Role is already account scoped.
                                      // Let's set account_id to null for the assignment if the role itself is carrying the scope context,
                                      // OR match the role's account_id.
                                      // Let's match the role's account_id to be safe and consistent with "Tenancy" requirement.
                'account_id' => $tenantId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (count($records) > 0) {
            AssignedPermission::insert($records);
        }
    }
}
