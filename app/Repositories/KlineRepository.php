<?php

namespace App\Repositories;

use App\Models\Kline;
use Illuminate\Database\Eloquent\Collection;

class KlineRepository
{
    /**
     * Find kline by symbol, interval, and open_time.
     */
    public function findByBucket(string $symbol, string $interval, int $openTime): ?Kline
    {
        return Kline::where('symbol', $symbol)
            ->where('interval', $interval)
            ->where('open_time', $openTime)
            ->first();
    }

    /**
     * Create a new kline.
     */
    public function create(array $data): Kline
    {
        return Kline::create($data);
    }

    /**
     * Get klines for a symbol and interval with time range.
     */
    public function getKlines(
        string $symbol,
        string $interval,
        int $startTime,
        int $endTime,
        int $limit = 1000
    ): Collection {
        return Kline::forSymbol($symbol)
            ->forInterval($interval)
            ->timeRange($startTime, $endTime)
            ->orderBy('open_time', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent klines.
     */
    public function getRecentKlines(string $symbol, string $interval, int $limit = 100): Collection
    {
        return Kline::forSymbol($symbol)
            ->forInterval($interval)
            ->orderBy('open_time', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Get the latest kline for a symbol and interval.
     */
    public function getLatestKline(string $symbol, string $interval): ?Kline
    {
        return Kline::forSymbol($symbol)
            ->forInterval($interval)
            ->orderBy('open_time', 'desc')
            ->first();
    }

    /**
     * Mark kline as closed.
     */
    public function markAsClosed(Kline $kline): void
    {
        $kline->markAsClosed();
    }

    /**
     * Get all unclosed klines that should be closed.
     */
    public function getUnclosedKlinesBefore(int $timestamp): Collection
    {
        return Kline::where('is_closed', false)
            ->where('close_time', '<=', $timestamp)
            ->get();
    }
}
