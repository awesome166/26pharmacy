<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('abacpermissions.tables', [
            'permissions' => 'permissions',
            'roles' => 'roles',
            'assigned_permissions' => 'assigned_permissions',
        ]);

        $permissionsTable = $tables['permissions'];
        $rolesTable = $tables['roles'];
        $assignedPermissionsTable = $tables['assigned_permissions'];

        $permission = DB::table($permissionsTable)->where('name', 'customers.manage')->first();

        if (!$permission) {
            $permissionId = (string) Str::ulid();
            DB::table($permissionsTable)->insert([
                'id' => $permissionId,
                'name' => 'customers.manage',
                'type' => 'crud',
                'description' => 'Manage customer records',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $permissionId = $permission->id;
        }

        $roles = DB::table($rolesTable)
            ->whereIn('name', ['Super Admin', 'Pharmacy Manager'])
            ->get(['id', 'account_id']);

        foreach ($roles as $role) {
            $exists = DB::table($assignedPermissionsTable)
                ->where('permission_id', $permissionId)
                ->where('assignee_id', $role->id)
                ->where('assignee_type', 'role')
                ->where(function ($q) use ($role) {
                    if ($role->account_id === null) {
                        $q->whereNull('account_id');
                    } else {
                        $q->where('account_id', $role->account_id);
                    }
                })
                ->exists();

            if (!$exists) {
                DB::table($assignedPermissionsTable)->insert([
                    'id' => (string) Str::ulid(),
                    'account_id' => $role->account_id,
                    'permission_id' => $permissionId,
                    'assignee_id' => $role->id,
                    'assignee_type' => 'role',
                    'access' => json_encode(['read', 'update', 'delete']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $tables = config('abacpermissions.tables', [
            'permissions' => 'permissions',
            'assigned_permissions' => 'assigned_permissions',
        ]);

        $permissionsTable = $tables['permissions'];
        $assignedPermissionsTable = $tables['assigned_permissions'];

        $permission = DB::table($permissionsTable)->where('name', 'customers.manage')->first();
        if (!$permission) {
            return;
        }

        DB::table($assignedPermissionsTable)
            ->where('permission_id', $permission->id)
            ->where('assignee_type', 'role')
            ->delete();

        DB::table($permissionsTable)->where('id', $permission->id)->delete();
    }
};
