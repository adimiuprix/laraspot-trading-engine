<?php

use App\Services\KlineService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule kline closure every minute to auto-close and broadcast finished candles
Schedule::command('kline:close-expired')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();