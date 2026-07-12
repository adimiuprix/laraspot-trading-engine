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
