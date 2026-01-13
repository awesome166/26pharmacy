<?php

namespace App\Services;

/**
 * Service for regulatory-grade audit trail management.
 */
class AuditService
{
    /**
     * Log an action to the audit trail.
     *
     * @param string $entityType
     * @param string $entityId
     * @param string $action
     * @param string|null $userId
     * @param array $metadata
     * @return void
     */
    public function log(string $entityType, string $entityId, string $action, ?string $userId = null, array $metadata = [])
    {
        \Illuminate\Support\Facades\DB::table('audit_trail')->insert([
            'audit_id' => \Illuminate\Support\Str::uuid(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'actor_user_id' => $userId,
            'timestamp' => now(),
            'metadata' => json_encode($metadata),
        ]);
    }

    /**
     * Retrieve audit history for a specific entity.
     *
     * @param string $entityType
     * @param string $entityId
     * @return \Illuminate\Support\Collection
     */
    public function getHistory(string $entityType, string $entityId)
    {
        return \Illuminate\Support\Facades\DB::table('audit_trail')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('timestamp', 'desc')
            ->get();
    }
}
