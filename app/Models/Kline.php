<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $symbol
 * @property string $interval
 * @property int $open_time
 * @property int $close_time
 * @property string $open
 * @property string $high
 * @property string $low
 * @property string $close
 * @property string $volume
 * @property string $quote_volume
 * @property int $trades_count
 * @property bool $is_closed
 */
#[Fillable([
    'symbol',
    'interval',
    'open_time',
    'close_time',
    'open',
    'high',
    'low',
    'close',
    'volume',
    'quote_volume',
    'trades_count',
    'is_closed',
])]
class Kline extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'open_time' => 'integer',
            'close_time' => 'integer',
            'open' => 'decimal:8',
            'high' => 'decimal:8',
            'low' => 'decimal:8',
            'close' => 'decimal:8',
            'volume' => 'decimal:8',
            'quote_volume' => 'decimal:8',
            'trades_count' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * Scope to filter by symbol.
     */
    public function scopeForSymbol(Builder $query, string $symbol): void
    {
        $query->where('symbol', $symbol);
    }

    /**
     * Scope to filter by interval.
     */
    public function scopeForInterval(Builder $query, string $interval): void
    {
        $query->where('interval', $interval);
    }

    /**
     * Scope to filter by time range.
     */
    public function scopeTimeRange(Builder $query, int $startTime, int $endTime): void
    {
        $query->where('open_time', '>=', $startTime)
            ->where('open_time', '<=', $endTime);
    }

    /**
     * Scope to get recent klines.
     */
    public function scopeRecent(Builder $query, int $limit = 100): void
    {
        $query->orderBy('open_time', 'desc')->limit($limit);
    }

    /**
     * Scope to get only closed klines.
     */
    public function scopeClosed(Builder $query): void
    {
        $query->where('is_closed', true);
    }

    /**
     * Update kline with new trade data.
     */
    public function updateWithTrade(string $price, string $amount, string $quoteAmount): void
    {
        // Update high
        if (bccomp($price, $this->high, 8) > 0) {
            $this->high = $price;
        }

        // Update low
        if (bccomp($price, $this->low, 8) < 0 || bccomp($this->low, '0', 8) === 0) {
            $this->low = $price;
        }

        // Update close (latest price)
        $this->close = $price;

        // Add volume
        $this->volume = bcadd($this->volume, $amount, 8);
        $this->quote_volume = bcadd($this->quote_volume, $quoteAmount, 8);

        // Increment trades count
        $this->trades_count++;

        $this->save();
    }

    /**
     * Mark kline as closed (interval completed).
     */
    public function markAsClosed(): void
    {
        $this->is_closed = true;
        $this->save();
    }

    /**
     * Get formatted data for API response (Binance format).
     * All values returned as strings for consistency.
     */
    public function toApiFormat(): array
    {
        return [
            (string) $this->open_time,      // Convert to string
            $this->open,                     // Already string from decimal cast
            $this->high,                     // Already string from decimal cast
            $this->low,                      // Already string from decimal cast
            $this->close,                    // Already string from decimal cast
            $this->volume,                   // Already string from decimal cast
            (string) $this->close_time,      // Convert to string
            $this->quote_volume,             // Already string from decimal cast
            (string) $this->trades_count,    // Convert to string
            '0',                             // Taker buy base asset volume (not implemented)
            '0',                             // Taker buy quote asset volume (not implemented)
            '0',                             // Ignore
        ];
    }
}
