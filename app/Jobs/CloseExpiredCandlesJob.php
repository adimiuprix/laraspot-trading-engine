<?php

namespace App\Jobs;

use App\Services\Trading\KlineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CloseExpiredCandlesJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(KlineService $klineService): void
    {
        // Close expired klines and automatically generate next ones
        $klineService->closeExpiredKlinesAndGenerateNext();
    }
}