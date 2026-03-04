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
            'accounting.dashboard.view' => ['type' => 'on-off', 'description' => 'View accounting dashboard'],
            'accounting.accounts.manage' => ['type' => 'crud', 'description' => 'Manage chart of accounts'],
            'accounting.journal.manage' => ['type' => 'crud', 'description' => 'Manage journal entries'],
            'accounting.cash.manage' => ['type' => 'crud', 'description' => 'Manage cash in and cash out journals'],
            'accounting.reports.view' => ['type' => 'on-off', 'description' => 'View accounting reports'],
            'accounting.close.manage' => ['type' => 'on-off', 'description' => 'Close daily books'],
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
                    'access' => str_contains($name, '.view') || str_contains($name, '.close')
                        ? json_encode(['on'])
                        : json_encode(['create', 'read', 'update', 'delete']),
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

        $names = [
            'accounting.dashboard.view',
            'accounting.accounts.manage',
            'accounting.journal.manage',
            'accounting.cash.manage',
            'accounting.reports.view',
            'accounting.close.manage',
        ];

        $permIds = DB::table($tables['permissions'])->whereIn('name', $names)->pluck('id');
        if ($permIds->isEmpty()) {
            return;
        }

        DB::table($tables['assigned_permissions'])->whereIn('permission_id', $permIds)->delete();
        DB::table($tables['permissions'])->whereIn('id', $permIds)->delete();
    }
};
