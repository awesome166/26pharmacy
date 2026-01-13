<?php

namespace App\Services;

/**
 * Service for caching projections locally for offline responsiveness.
 */
class BranchLocalCacheService
{
    /**
     * Get cached stock level for immediate UI response.
     *
     * @param string $batchId
     * @return int|null
     */
    public function getLocalStock(string $batchId)
    {
        return \Illuminate\Support\Facades\Cache::get("stock:{$batchId}");
    }

    /**
     * Invalidate local cache for a specific domain entity.
     *
     * @param string $entityType
     * @param string $entityId
     * @return void
     */
    public function invalidatePath(string $entityType, string $entityId)
    {
        \Illuminate\Support\Facades\Cache::forget("{$entityType}:{$entityId}");
    }
}
