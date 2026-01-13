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

    public function __construct(string $branchId, string $date)
    {
        $this->branchId = $branchId;
        $this->date = $date;
    }

    public function handle(ReportingService $reportingService)
    {
        $reportingService->getDailySummary($this->branchId, $this->date);
    }
}
