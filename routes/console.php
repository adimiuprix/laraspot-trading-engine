<?php

use App\Jobs\CloseExpiredCandlesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule kline closure every minute to auto-close expired candles and generate new ones
Schedule::job(new CloseExpiredCandlesJob)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();