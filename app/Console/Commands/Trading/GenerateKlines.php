<?php

namespace App\Console\Commands\Trading;

use App\Services\KlineService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Argument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Option;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kline:generate')]
#[Description('Generate empty klines for a trading pair (pre-generate for future trading)')]
#[Argument('symbol', 'Trading pair symbol (e.g., BTC/USDT)')]
#[Option('intervals', mode: 'optional', description: 'Comma-separated intervals (default: all configured intervals)')]
#[Option('days', mode: 'optional', description: 'Number of days to generate ahead (default: 7)')]
#[Option('price', mode: 'optional', description: 'Initial price for klines (default: 0)')]
class GenerateKlines extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(KlineService $klineService): int
    {
        $symbol = $this->argument('symbol');
        $days = (int) $this->option('days') ?: 7;
        $initialPrice = $this->option('price') ?: '0';

        // Parse intervals
        $intervalsOption = $this->option('intervals');
        $intervals = $intervalsOption
            ? explode(',', $intervalsOption)
            : array_keys(config('trading.kline_intervals', ['1m' => 60]));

        $this->info("Generating klines for {$symbol}");
        $this->info('Intervals: '.implode(', ', $intervals));
        $this->info("Period: {$days} days ahead");
        $this->info("Initial price: {$initialPrice}");
        $this->newLine();

        // Calculate time range
        $startTime = Carbon::now()->startOfMinute()->timestamp * 1000;
        $endTime = Carbon::now()->addDays($days)->timestamp * 1000;

        $this->info('Generating klines...');
        $progressBar = $this->output->createProgressBar(count($intervals));
        $progressBar->start();

        $results = [];
        foreach ($intervals as $interval) {
            $interval = trim($interval);
            $intervalSeconds = config("trading.kline_intervals.{$interval}");

            if (! $intervalSeconds) {
                $this->error("Invalid interval: {$interval}");

                continue;
            }

            $generated = $klineService->generateEmptyKlines(
                $symbol,
                $interval,
                $startTime,
                $endTime,
                $initialPrice
            );

            $results[$interval] = $generated;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('✓ Klines generated successfully!');
        $this->newLine();

        $this->table(
            ['Interval', 'Generated'],
            collect($results)->map(fn ($count, $interval) => [$interval, $count])->toArray()
        );

        $totalGenerated = array_sum($results);
        $this->info("Total: {$totalGenerated} klines generated");

        return self::SUCCESS;
    }
}
