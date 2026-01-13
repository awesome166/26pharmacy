<?php

namespace App\Services;

/**
 * Service for managing tenants (pharmacy groups).
 */
class TenantService
{
    /**
     * Create a new tenant.
     *
     * @param array $data
     * @return object
     */
    public function createTenant(array $data)
    {
        $id = \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::table('tenants')->insert([
            'tenant_id' => $id,
            'legal_name' => $data['legal_name'],
            'tax_identifier' => $data['tax_identifier'],
            'regulatory_metadata' => json_encode($data['regulatory_metadata'] ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['tenant_id' => $id];
    }

    /**
     * Get tenant by ID.
     *
     * @param string $tenantId
     * @return object|null
     */
    public function getTenant(string $tenantId)
    {
        return \Illuminate\Support\Facades\DB::table('tenants')
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Update tenant details.
     *
     * @param string $tenantId
     * @param array $data
     * @return bool
     */
    public function updateTenant(string $tenantId, array $data)
    {
        return (bool) \Illuminate\Support\Facades\DB::table('tenants')
            ->where('tenant_id', $tenantId)
            ->update(array_merge($data, ['updated_at' => now()]));
    }

    /**
     * Get all tenants (Paginated).
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllTenants(int $perPage = 15)
    {
        return \Illuminate\Support\Facades\DB::table('tenants')->paginate($perPage);
    }

    /**
     * Delete a tenant.
     *
     * @param string $tenantId
     * @return bool
     */
    public function deleteTenant(string $tenantId)
    {
        return (bool) \Illuminate\Support\Facades\DB::table('tenants')
            ->where('tenant_id', $tenantId)
            ->delete();
    }
}
