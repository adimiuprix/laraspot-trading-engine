<?php

namespace App\Services\Trading;

use App\Events\KlineUpdated;
use App\Models\Kline;

class KlineService
{
    /**
     * Ambil kline terakhir yang masih terbuka (is_closed = 0).
     */
    public function getLatestUnclosed(string $symbol, string $interval): ?Kline
    {
        return Kline::where('symbol', $symbol)
            ->where('interval', $interval)
            ->where('is_closed', false)
            ->orderByDesc('open_time')
            ->first();
    }

    /**
     * Tutup semua kline yang waktu close_time nya sudah lewat.
     */
    public function closeExpired(string $symbol, string $interval): int
    {
        $now = now()->timestamp;

        return Kline::where('symbol', $symbol)
            ->where('interval', $interval)
            ->where('is_closed', false)
            ->where('close_time', '<=', $now)
            ->update(['is_closed' => true]);
    }

    /**
     * Tutup semua kline yang expired dan generate kline berikutnya.
     * Method ini akan:
     * 1. Update is_closed = true untuk kline yang close_time <= now
     * 2. Generate kline baru dengan OHLC dari close price sebelumnya
     */
    public function closeExpiredKlinesAndGenerateNext(): void
    {
        $now = now()->timestamp;

        // Ambil semua kombinasi symbol-interval yang unik dari kline yang akan expired
        $expiredKlines = Kline::where('is_closed', false)
            ->where('close_time', '<=', $now)
            ->get(['symbol', 'interval'])
            ->unique(fn($kline) => $kline->symbol . '-' . $kline->interval);

        foreach ($expiredKlines as $kline) {
            // 1. Tutup semua kline yang expired untuk symbol-interval ini
            $closedCount = $this->closeExpired($kline->symbol, $kline->interval);

            if ($closedCount > 0) {
                // 2. Generate kline baru untuk periode berikutnya
                $this->generateNextKline($kline->symbol, $kline->interval);
            }
        }
    }

    /**
     * Generate kline baru untuk periode berikutnya.
     */
    private function generateNextKline(string $symbol, string $interval): Kline
    {
        // Ambil kline terakhir yang sudah ditutup
        $lastKline = Kline::where('symbol', $symbol)
            ->where('interval', $interval)
            ->where('is_closed', true)
            ->orderByDesc('close_time')
            ->first();

        // Hitung durasi interval dari config
        $intervalSeconds = config("trading.kline_intervals.{$interval}", 60);

        // Hitung waktu untuk kline baru (menggunakan timestamp, bukan millisecond)
        $openTime = $lastKline->close_time + 1;
        $closeTime = $openTime + $intervalSeconds - 1;

        // Gunakan harga close dari kline sebelumnya, atau '0' jika tidak ada
        $price = $lastKline ? $lastKline->close : '0';

        $newKline = Kline::create([
            'symbol' => $symbol,
            'interval' => $interval,
            'open_time' => $openTime,
            'close_time' => $closeTime,
            'open' => $price,
            'high' => $price,
            'low' => $price,
            'close' => $price,
            'volume' => '0',
            'quote_volume' => '0',
            'trades_count' => 0,
            'is_closed' => false,
        ]);

        // Broadcast event untuk kline baru
        KlineUpdated::dispatch($newKline);

        return $newKline;
    }
}
