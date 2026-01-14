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

class PermissionController extends Controller
{
    public function index()
    {
        return response()->json(Permission::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'group' => 'nullable|string',
            'guard_name' => 'nullable|string'
        ]);

        $permission = Permission::create($data);
        return response()->json($permission, 201);
    }

    public function show($id)
    {
        return response()->json(Permission::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $id,
            'group' => 'nullable|string',
            'guard_name' => 'nullable|string'
        ]);

        $permission->update($data);

        // Re-cache permissions for all users that have this permission
        $users = User::permission($permission->name)->get();
        foreach ($users as $user) {
            AccessControlService::cacheUserPermissions($user);
        }

        return response()->json($permission);
    }

    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);

        // Re-cache users before deletion
        $users = User::permission($permission->name)->get();
        foreach ($users as $user) {
            AccessControlService::cacheUserPermissions($user);
        }

        $permission->delete();
        return response()->json(['message' => 'Permission deleted.']);
    }
}


// Suggested routes/web.php
// Route::resource('permissions', PermissionController::class);
