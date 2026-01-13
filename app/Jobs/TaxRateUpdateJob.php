<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\TaxService;

/**
 * Job to update tax rates asynchronously.
 */
class TaxRateUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'notifications';

    protected $taxData;

    public function __construct(array $taxData)
    {
        $this->taxData = $taxData;
    }

    public function handle(TaxService $taxService)
    {
        $taxService->updateTaxRates($this->taxData);
    }
}
