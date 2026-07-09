# Kline (OHLCV) System Documentation

## Overview

The Kline system generates real-time candlestick (OHLCV) data from executed trades. This implementation follows the guide from `OHLCV_Real_Time_Guide.md`.

**Current Status:** Only **1-minute (1m) interval** is implemented. Other intervals can be added later by uncommenting them in `config/trading.php`.

## Architecture

```
Trade Executed
    ↓
TradeExecuted Event
    ↓
UpdateKlineOnTradeExecuted Listener (Queued)
    ↓
KlineService::updateAllIntervalsFromTrade()
    ↓
For each interval (1m, 5m, 1h, 1d, etc.):
    ↓
    Calculate bucket time
    ↓
    Find or Create Kline
    ↓
    Update OHLCV values
    ↓
    Broadcast KlineUpdated Event
```

## Key Concepts

### 1. Bucket Time Calculation

```php
bucket = floor(timestamp / interval) * interval
```

**Example:**
```
timestamp = 1752043517 (seconds)
interval = 60 (1 minute)

bucket = floor(1752043517 / 60) * 60
       = 1752043500

All trades from 1752043500 to 1752043559 go into the same candle
```

### 2. OHLCV Formation Rules

#### First Trade in Interval (New Candle):
```php
open   = price
high   = price
low    = price
close  = price
volume = amount
```

#### Subsequent Trades (Update Candle):
```php
open   = unchanged  // NEVER changes
high   = max(high, price)
low    = min(low, price)
close  = price  // Always latest
volume = volume + amount
```

### 3. Supported Intervals

**Currently Active:**
```php
'1m'  => 60 seconds  // 1 minute
```

**Available for Future (uncomment in config/trading.php):**
```php
// '3m'  => 180 seconds
// '5m'  => 300 seconds
// '15m' => 900 seconds
// '30m' => 1800 seconds
// '1h'  => 3600 seconds
// '2h'  => 7200 seconds
// '4h'  => 14400 seconds
// '6h'  => 21600 seconds
// '8h'  => 28800 seconds
// '12h' => 43200 seconds
// '1d'  => 86400 seconds
// '3d'  => 259200 seconds
// '1w'  => 604800 seconds
// '1M'  => 2592000 seconds
```

## Database Schema

```sql
CREATE TABLE klines (
    id BIGINT PRIMARY KEY,
    symbol VARCHAR(20),           -- e.g., 'BTC/USDT'
    interval VARCHAR(10),          -- e.g., '1m', '1h'
    open_time BIGINT,              -- Unix timestamp (seconds)
    close_time BIGINT,             -- open_time + interval - 1
    
    -- OHLCV Data
    open DECIMAL(20,8),
    high DECIMAL(20,8),
    low DECIMAL(20,8),
    close DECIMAL(20,8),
    volume DECIMAL(20,8),          -- Base asset volume
    quote_volume DECIMAL(20,8),    -- Quote asset volume
    
    trades_count INT,              -- Number of trades
    is_closed BOOLEAN,             -- Interval completed?
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE (symbol, interval, open_time)
);
```

## API Endpoints

### Get Klines

```http
GET /api/klines/{symbol}?interval=1m&startTime={start}&endTime={end}&limit={limit}
```

**Parameters:**
- `interval` (required): `1m` (only 1-minute interval currently supported)
- `startTime` (optional): Unix timestamp in seconds
- `endTime` (optional): Unix timestamp in seconds
- `limit` (optional): Number of klines (default: 500, max: 1000)

**Example:**
```bash
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m&limit=100"
```

**Response Format (Binance-compatible):**
```json
[
  [
    1752043500,          // Open time (seconds)
    "50000.00000000",    // Open
    "50500.00000000",    // High
    "49800.00000000",    // Low
    "50200.00000000",    // Close
    "1.50000000",        // Volume (base asset)
    1752043559,          // Close time (seconds)
    "75300.00000000",    // Quote volume
    10,                  // Number of trades
    "0",                 // Taker buy base volume (not implemented)
    "0",                 // Taker buy quote volume (not implemented)
    "0"                  // Ignore
  ]
]
```

## WebSocket Events

### Subscribe to Kline Updates

```javascript
import { echo } from '@laravel/echo-vue';

// Subscribe to BTC/USDT 1-minute klines
echo.channel('kline.BTC-USDT.1m')
    .listen('.kline.updated', (event) => {
        console.log('Kline updated:', event.k);
        
        // event.k contains:
        // {
        //   t: 1752043500,    // Open time (ms)
        //   T: 1752043559999, // Close time (ms)
        //   s: "BTC/USDT",    // Symbol
        //   i: "1m",          // Interval
        //   o: "50000",       // Open
        //   h: "50500",       // High
        //   l: "49800",       // Low
        //   c: "50200",       // Close
        //   v: "1.5",         // Volume
        //   q: "75300",       // Quote volume
        //   n: 10,            // Trades count
        //   x: false          // Is closed?
        // }
    });
```

## Artisan Commands

### Close Expired Klines

Marks klines as closed when their interval has passed.

```bash
php artisan klines:close-expired
```

**Scheduled automatically every minute** in `routes/console.php`.

### Fill Gaps

Creates empty klines for intervals with no trades.

```bash
php artisan klines:fill-gaps {symbol} {interval} --start={timestamp} --end={timestamp}
```

**Example:**
```bash
php artisan klines:fill-gaps "BTC/USDT" 1m --start=1752000000 --end=1752043600
```

## Testing

### Run Tests

```bash
php artisan test --filter=KlineTest
```

### Seed Test Data

```bash
php artisan db:seed --class=KlineTestSeeder
```

This will:
1. Create test users
2. Give them balances
3. Place orders that match
4. Generate trades (which create klines)

Then check results:
```bash
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m"
```

## Performance Considerations

### 1. Async Processing

Kline updates are processed asynchronously via queues:

```php
class UpdateKlineOnTradeExecuted implements ShouldQueue
{
    // Doesn't block trade execution
}
```

**Start queue worker:**
```bash
php artisan queue:work
```

### 2. Caching

For frequently accessed klines:

```php
$klines = Cache::remember("klines:{$symbol}:{$interval}", 60, function () {
    return Kline::forSymbol($symbol)
        ->forInterval($interval)
        ->recent(1000)
        ->get();
});
```

### 3. Database Indexes

Already created in migration:
```sql
INDEX klines_query_index (symbol, interval, open_time)
UNIQUE klines_symbol_interval_time (symbol, interval, open_time)
```

## Gap Filling

If no trades occur in an interval, you can create empty klines:

```php
$klineService->fillGaps('BTC/USDT', '1m', $startTime, $endTime);
```

Empty kline values:
```
Open  = Close of previous candle
High  = Open
Low   = Open
Close = Open
Volume = 0
```

## Troubleshooting

### Klines not updating

1. **Check queue is running:**
   ```bash
   php artisan queue:work
   ```

2. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Verify trades exist:**
   ```sql
   SELECT * FROM trades WHERE symbol = 'BTC/USDT' ORDER BY created_at DESC LIMIT 10;
   ```

### WebSocket not broadcasting

1. **Check Reverb is running:**
   ```bash
   php artisan reverb:start
   ```

2. **Verify event listener is registered:**
   ```php
   // bootstrap/providers.php
   EventServiceProvider::class,
   ```

## Production Recommendations

1. **Use Redis for Queue:**
   ```env
   QUEUE_CONNECTION=redis
   ```

2. **Use Supervisor to keep queue running:**
   ```ini
   [program:scavence-queue]
   command=php /path/to/artisan queue:work --tries=3
   autostart=true
   autorestart=true
   ```

3. **Schedule kline closure:**
   ```bash
   # crontab
   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
   ```

4. **Consider time-series database for historical data:**
   - InfluxDB
   - TimescaleDB
   - Apache Druid

## References

- [OHLCV_Real_Time_Guide.md](../OHLCV_Real_Time_Guide.md) - Original specification
- [Binance Kline API](https://binance-docs.github.io/apidocs/spot/en/#kline-candlestick-data) - API format reference
