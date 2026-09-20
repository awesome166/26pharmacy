<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\SyncService;
use AbacPermissions\Models\Account;
use AbacPermissions\Tenancy\TenantContext;
use RuntimeException;

/**
 * Job to synchronize local events to the cloud.
 */
class SyncEventsToCloudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $maxExceptions = 3;

    public function __construct(
        public readonly ?string $accountId = null,
        public readonly ?string $deviceId = null,
    )
    {
        $this->queue = 'sync-outbox';
        $this->connection = config('sync.queue_connection', 'database');
    }

    public function handle(SyncService $syncService): void
    {
        if ($this->accountId) {
            $account = Account::query()->findOrFail($this->accountId);
            app(TenantContext::class)->setAccount($account);
        }

        $result = $syncService->forAccount($this->accountId, $this->deviceId)->push();
        if (!($result['ok'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Cloud sync push failed.');
        }
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::error('SyncEventsToCloudJob failed after all retries', [
            'error' => $exception->getMessage(),
        ]);
    }
}
