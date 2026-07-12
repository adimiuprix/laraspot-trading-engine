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

// Auto-generate klines for the next day at midnight
// This ensures klines always exist for upcoming trading periods
Schedule::call(function () {
    $symbols = ['BTCUSDT']; // Configure your trading pairs
    $klineService = app(KlineService::class);

    foreach ($symbols as $symbol) {
        $intervals = array_keys(config('trading.kline_intervals'));

        foreach ($intervals as $interval) {
            // Generate klines for next 2 days
            $klineService->generateKlinesAhead($symbol, $interval, periods: 2880, initialPrice: '0');
        }
    }
})->dailyAt('00:00')->name('auto-generate-klines');
