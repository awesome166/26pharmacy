<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AuditService;

/**
 * Job to log an entry into the audit trail asynchronously.
 */
class AuditEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $auditData;

    public function __construct(array $auditData)
    {
        $this->auditData = $auditData;
        $this->queue = 'audit-trail';
    }

    public function handle(AuditService $auditService)
    {
        $auditService->log(
            $this->auditData['entity_type'],
            $this->auditData['entity_id'],
            $this->auditData['action'],
            $this->auditData['actor_user_id'] ?? null,
            $this->auditData['metadata'] ?? []
        );
    }
}
