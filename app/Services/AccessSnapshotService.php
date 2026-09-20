<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AccessSnapshotService
{
    public function export(string $accountId): array
    {
        $userIds = DB::table('account_user')->where('account_id', $accountId)->pluck('user_id');
        $roleIds = DB::table('roles')->where('account_id', $accountId)->pluck('id')
            ->merge(DB::table('role_user')->whereIn('user_id', $userIds)->pluck('role_id'))
            ->unique()->values();

        $assignments = DB::table('assigned_permissions')
            ->where(fn ($query) => $query->where('account_id', $accountId)->orWhereNull('account_id'))
            ->where(function ($query) use ($userIds, $roleIds) {
                $query->where(fn ($q) => $q->where('assignee_type', 'user')->whereIn('assignee_id', $userIds))
                    ->orWhere(fn ($q) => $q->where('assignee_type', 'role')->whereIn('assignee_id', $roleIds));
            })->get();
        $permissionIds = $assignments->pluck('permission_id')->unique()->values();

        return [
            'account_id' => $accountId,
            // Password hashes are required for offline verification. This
            // snapshot is only exposed through authenticated TLS sync routes.
            'users' => $this->rows(DB::table('users')->whereIn('id', $userIds)->get([
                'id', 'name', 'email', 'password', 'email_verified_at', 'is_active', 'created_at', 'updated_at',
            ])),
            'memberships' => $this->rows(DB::table('account_user')->where('account_id', $accountId)->get()),
            'roles' => $this->rows(DB::table('roles')->whereIn('id', $roleIds)->get()),
            'role_users' => $this->rows(DB::table('role_user')
                ->whereIn('user_id', $userIds)->whereIn('role_id', $roleIds)->get()),
            'permissions' => $this->rows(DB::table('permissions')->whereIn('id', $permissionIds)->get()),
            'assigned_permissions' => $this->rows($assignments),
        ];
    }

    public function import(array $snapshot, string $accountId): void
    {
        if (($snapshot['account_id'] ?? null) !== $accountId) {
            throw new \RuntimeException('Access snapshot does not belong to this pharmacy account.');
        }

        DB::transaction(function () use ($snapshot, $accountId) {
            $users = $snapshot['users'] ?? [];
            $userIds = collect($users)->pluck('id')->filter()->values();
            $localRoleIds = DB::table('roles')->where(fn ($q) => $q->where('account_id', $accountId)->orWhereNull('account_id'))
                ->pluck('id');

            if ($userIds->isNotEmpty() && $localRoleIds->isNotEmpty()) {
                DB::table('role_user')->whereIn('user_id', $userIds)->whereIn('role_id', $localRoleIds)->delete();
            }
            if ($userIds->isNotEmpty()) {
                DB::table('assigned_permissions')
                    ->where(fn ($q) => $q->where('account_id', $accountId)->orWhereNull('account_id'))
                    ->where(fn ($q) => $q->where(fn ($u) => $u->where('assignee_type', 'user')->whereIn('assignee_id', $userIds))
                        ->orWhere(fn ($r) => $r->where('assignee_type', 'role')->whereIn('assignee_id', $localRoleIds)))
                    ->delete();
            }
            DB::table('account_user')->where('account_id', $accountId)->delete();
            if ($localRoleIds->isNotEmpty()) {
                DB::table('roles')->where('account_id', $accountId)->delete();
            }

            $this->upsert($users, 'users', ['id']);
            $this->upsert($snapshot['roles'] ?? [], 'roles', ['id']);
            $this->upsert($snapshot['permissions'] ?? [], 'permissions', ['id']);
            $this->insertOrIgnore($snapshot['memberships'] ?? [], 'account_user');
            $this->insertOrIgnore($snapshot['role_users'] ?? [], 'role_user');
            $this->upsert($snapshot['assigned_permissions'] ?? [], 'assigned_permissions', ['id']);
        });
    }

    private function rows(iterable $rows): array
    {
        return collect($rows)->map(fn ($row) => (array) $row)->values()->all();
    }

    private function upsert(array $rows, string $table, array $uniqueBy): void
    {
        if (!$rows) return;
        $columns = array_values(array_diff(array_keys($rows[0]), $uniqueBy));
        DB::table($table)->upsert($rows, $uniqueBy, $columns);
    }

    private function insertOrIgnore(array $rows, string $table): void
    {
        if ($rows) DB::table($table)->insertOrIgnore($rows);
    }
}
