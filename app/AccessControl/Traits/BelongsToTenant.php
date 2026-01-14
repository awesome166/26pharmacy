<?php

namespace App\AccessControl\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;

/**
 * Trait BelongsToTenant
 *
 * This trait is intended to be used by Eloquent models that "belong to" a tenant (multi-tenancy support).
 * It automatically assigns the current tenant's account_id to new records and ensures that all queries
 * are scoped to the current tenant, unless otherwise specified.
 *
 * How it works:
 * - When a model using this trait is being created, if the account_id is not already set and there is a
 *   current tenant in the application container, it sets the model's account_id to the current tenant's id.
 * - It adds a global query scope so that all queries on the model are automatically filtered to only include
 *   records belonging to the current tenant (by matching account_id).
 * - It provides a local query scope `byTenant` to allow explicit filtering by a given tenant id, or by the
 *   current tenant if no id is provided.
 */
trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for a model.
     * Sets up model event and global scope for tenant isolation.
     */
    protected static function bootBelongsToTenant()
    {
        // Automatically set tenant_id to current tenant's id on creation, if not already set
        static::creating(function ($model) {
            if (!$model->tenant_id && App::has('currentTenant')) {
                $model->tenant_id = App::get('currentTenant')->tenant_id;
            }
        });

        // Add a global scope to ensure all queries are filtered by the current tenant's tenant_id
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (App::has('currentTenant')) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    App::get('currentTenant')->tenant_id
                );
            }
        });
    }

    /**
     * Local scope to filter queries by a specific tenant.
     *
     * @param Builder $query
     * @param string|null $tenantId
     * @return Builder
     */
    public function scopeByTenant(Builder $query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? App::get('currentTenant')->tenant_id);
    }
}
