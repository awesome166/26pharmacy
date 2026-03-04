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
        $assignedTable = $tables['assigned_permissions'];

        $defs = [
            'financial.view' => ['type' => 'on-off', 'description' => 'View accounting dashboard and reports'],
            'financial.manage' => ['type' => 'crud', 'description' => 'Manage chart of accounts, journals and book closing'],
        ];

        $permIds = [];
        foreach ($defs as $name => $meta) {
            $existing = DB::table($permissionsTable)->where('name', $name)->first();
            if ($existing) {
                $permIds[$name] = $existing->id;
                continue;
            }

            $id = (string) Str::ulid();
            DB::table($permissionsTable)->insert([
                'id' => $id,
                'name' => $name,
                'type' => $meta['type'],
                'description' => $meta['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $permIds[$name] = $id;
        }

        $roles = DB::table($rolesTable)->whereIn('name', ['Super Admin', 'Pharmacy Manager'])->get(['id', 'account_id']);

        foreach ($roles as $role) {
            foreach ($permIds as $name => $permId) {
                $exists = DB::table($assignedTable)
                    ->where('permission_id', $permId)
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

                if ($exists) {
                    continue;
                }

                DB::table($assignedTable)->insert([
                    'id' => (string) Str::ulid(),
                    'account_id' => $role->account_id,
                    'permission_id' => $permId,
                    'assignee_id' => $role->id,
                    'assignee_type' => 'role',
                    'access' => $name === 'financial.view' ? json_encode(['on']) : json_encode(['create', 'read', 'update', 'delete']),
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
        $assignedTable = $tables['assigned_permissions'];

        $permIds = DB::table($permissionsTable)
            ->whereIn('name', ['financial.view', 'financial.manage'])
            ->pluck('id');

        if ($permIds->isEmpty()) {
            return;
        }

        DB::table($assignedTable)->whereIn('permission_id', $permIds)->delete();
        DB::table($permissionsTable)->whereIn('id', $permIds)->delete();
    }
};
