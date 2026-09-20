<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ReportingService;

/**
 * Job to calculate and store the daily financial summary for branches.
 */
class DailyFinancialSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'reporting';

    protected $branchId;
    protected $date;
    protected $accountId;

    public function __construct(string $branchId, string $date, ?string $accountId = null)
    {
        $this->branchId = $branchId;
        $this->date = $date;
        $this->accountId = $accountId;
    }

    public function handle(ReportingService $reportingService)
    {
        $accountId = $this->accountId ?: \Illuminate\Support\Facades\DB::table('branches')
            ->where('branch_id', $this->branchId)->value('account_id');
        $account = \AbacPermissions\Models\Account::query()->findOrFail($accountId);
        app(\AbacPermissions\Tenancy\TenantContext::class)->setAccount($account);
        $reportingService->getDailySummary($this->date, $this->branchId, true);
    }
}
