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
     * Get formatted data for API response (Binance format).
     * All values returned as strings for consistency.
     */
    public function toApiFormat(): array
    {
        return [
            (string) $this->open_time,
            $this->open,
            $this->high,
            $this->low,
            $this->close,
            $this->volume,
            (string) $this->close_time,
            $this->quote_volume,
            (string) $this->trades_count,
            '0',
            '0',
            '0',
        ];
    }
}
