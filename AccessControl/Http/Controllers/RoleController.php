<?php

// (existing middleware, provider, command, and seeder remain the same)

namespace App\AccessControl\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\AccessControl\Models\Permission;
use App\AccessControl\Models\Role;
use Illuminate\Support\Facades\Cache;
use App\AccessControl\Services\AccessControlService;
use App\Models\User;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(Role::with('permissions')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'guard_name' => 'nullable|string',
            'tenant_id' => 'nullable|exists:tenants,id'
        ]);

        $role = Role::create($data);
        return response()->json($role, 201);
    }

    public function show($id)
    {
        return response()->json(Role::with('permissions')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
            'guard_name' => 'nullable|string'
        ]);

        $role->update($data);
        return response()->json($role);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Re-cache users before deletion
        $users = User::role($role->name)->get();
        foreach ($users as $user) {
            AccessControlService::cacheUserPermissions($user);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted.']);
    }

    public function assignPermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        $data = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        $role->permissions()->sync($data['permissions']);

        // Re-cache all users of the role
        $users = User::role($role->name)->get();
        foreach ($users as $user) {
            AccessControlService::cacheUserPermissions($user);
        }

        return response()->json(['message' => 'Permissions updated.']);
    }
}
