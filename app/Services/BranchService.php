<?php

namespace App\Services;

/**
 * Service for managing pharmacy branches.
 */
class BranchService
{
    /**
     * Create a new branch for a tenant.
     *
     * @param string $tenantId
     * @param array $data
     * @return object
     */
    public function createBranch(string $tenantId, array $data)
    {
        $id = \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('branches')->insert([
            'branch_id' => $id,
            'tenant_id' => $tenantId,
            'branch_name' => $data['branch_name'],
            'physical_address' => $data['physical_address'] ?? null,
            'license_number' => $data['license_number'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['branch_id' => $id];
    }

    /**
     * Get branches for a specific tenant.
     *
     * @param string $tenantId
     * @return \Illuminate\Support\Collection
     */
    public function getTenantBranches(string $tenantId)
    {
        return \Illuminate\Support\Facades\DB::table('branches')
            ->where('tenant_id', $tenantId)
            ->get();
    }

    /**
     * Retrieve a specific branch.
     *
     * @param string $branchId
     * @return object|null
     */
    public function getBranch(string $branchId)
    {
        return \Illuminate\Support\Facades\DB::table('branches')
            ->where('branch_id', $branchId)
            ->first();
    }

    /**
     * Update branch details.
     *
     * @param string $branchId
     * @param array $data
     * @return bool
     */
    public function updateBranch(string $branchId, array $data)
    {
        return (bool) \Illuminate\Support\Facades\DB::table('branches')
            ->where('branch_id', $branchId)
            ->update(array_merge($data, ['updated_at' => now()]));
    }

    /**
     * Delete a branch.
     *
     * @param string $branchId
     * @return bool
     */
    public function deleteBranch(string $branchId)
    {
        return (bool) \Illuminate\Support\Facades\DB::table('branches')
            ->where('branch_id', $branchId)
            ->delete();
    }
}
