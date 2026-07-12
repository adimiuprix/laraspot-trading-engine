<?php

namespace App\Console\Commands\Trading;

use App\Services\KlineService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kline:close-expired')]
#[Description('Close expired klines (mark as closed when interval has passed)')]
class CloseExpiredKlines extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(KlineService $klineService): int
    {
        $this->info('Closing expired klines...');

        $closed = $klineService->closeAllExpiredKlines();

        if ($closed > 0) {
            $this->info("✓ Closed {$closed} expired klines");
        } else {
            $this->info('✓ No expired klines to close');
        }

        return self::SUCCESS;
    }
}
