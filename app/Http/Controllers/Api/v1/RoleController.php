<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// use AbacPermissions\Models\Role;
use App\Models\Role;
// use AbacPermissions\Models\Role;
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

        $roles = $query->getPermissionsWithAccess()->paginate(15);

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
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*.id' => 'required|exists:permissions,id',
            'permissions.*.access' => 'nullable|array',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
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
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*.id' => 'required|exists:permissions,id',
            'permissions.*.access' => 'nullable|array',
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
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
    protected function syncPermissions(Role $role, array $permissions, ?string $tenantId)
    {
        // Use Eloquent syncWithPivotValues to handle:
        // 1. Pivot ID generation (via AssignedPermission model)
        // 2. Assignee ID and Type (via morphToMany relationship)
        // 3. Additional columns like account_id

        $syncPayload = [];
        foreach ($permissions as $perm) {
            $syncPayload[$perm['id']] = [
                'account_id' => $tenantId,
                'assignee_type' => 'role',
                'access' => isset($perm['access']) ? json_encode($perm['access']) : null, // Store access as JSON
            ];
        }

        $role->permissions()->sync($syncPayload);
    }
}
