<?php

namespace App\Services;

/**
 * Service for ensuring schema evolution for events is backward compatible.
 */
class EventVersioningService
{
    /**
     * Migrate an event payload to the latest version.
     *
     * @param string $eventType
     * @param int $fromVersion
     * @param array $payload
     * @return array Upgraded payload
     */
    public function upgradePayload(string $eventType, int $fromVersion, array $payload)
    {
        $currentVersion = $fromVersion;
        $targetVersion = $this->getLatestVersion($eventType);

        while ($currentVersion < $targetVersion) {
            $method = "migrate" . str_replace('_', '', $eventType) . "V{$currentVersion}ToV" . ($currentVersion + 1);
            if (method_exists($this, $method)) {
                $payload = $this->$method($payload);
            }
            $currentVersion++;
        }

        return $payload;
    }

    /**
     * Get the latest schema version for an event type.
     *
     * @param string $eventType
     * @return int
     */
    public function getLatestVersion(string $eventType)
    {
        $registry = [
            'SALE_FINALIZED' => 2,
            'STOCK_ADJUSTED' => 1
        ];
        return $registry[$eventType] ?? 1;
    }
}
