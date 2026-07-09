# Kline System - Quick Start Guide (1-Minute Interval Only)

## 🚀 Getting Started in 5 Minutes

**Note:** Sistem ini saat ini hanya mendukung **interval 1 menit (1m)**. Interval lain seperti 5m, 1h, 1d akan ditambahkan nanti setelah sistem 1m stabil.

### Step 1: Run Migration

```bash
php artisan migrate
```

This creates the `klines` table.

### Step 2: Start Queue Worker

Kline updates are processed asynchronously:

```bash
php artisan queue:work
```

**Keep this running in a separate terminal.**

### Step 3: Place Some Orders

The klines are automatically generated when trades execute.

#### Option A: Use Test Seeder

```bash
php artisan db:seed --class=KlineTestSeeder
```

Wait a few seconds for the queue to process, then check:

```bash
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m"
```

#### Option B: Manual Trading

1. Create users and give them balance
2. Place buy and sell orders that match
3. Trades will automatically generate klines

### Step 4: View Klines via API

**Get 1-minute candles (default limit: 500):**
```bash
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m"
```

**Get last 10 candles:**
```bash
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m&limit=10"
```

**Get specific time range:**
```bash
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m&startTime=1752000000&endTime=1752003600"
```

**Note:** Saat ini hanya `interval=1m` yang didukung.

### Step 5: Real-time Updates (WebSocket)

**Start Reverb:**
```bash
php artisan reverb:start
```

**Frontend subscription (hanya 1-minute interval):**
```javascript
import { echo } from '@laravel/echo-vue';

// Subscribe to BTC/USDT 1-minute klines
echo.channel('kline.BTC-USDT.1m')
    .listen('.kline.updated', (event) => {
        console.log('New 1-minute candle data:', event.k);
        // Update your chart here
    });
```

---

## 📊 Understanding the Response

API returns array of arrays (Binance-compatible format):

```json
[
  [
    1752043500,          // [0] Open time (Unix timestamp)
    "50000.00000000",    // [1] Open price
    "50500.00000000",    // [2] High price
    "49800.00000000",    // [3] Low price
    "50200.00000000",    // [4] Close price
    "1.50000000",        // [5] Volume (BTC)
    1752043559,          // [6] Close time
    "75300.00000000",    // [7] Quote volume (USDT)
    10,                  // [8] Number of trades
    "0",                 // [9] Taker buy base volume
    "0",                 // [10] Taker buy quote volume
    "0"                  // [11] Ignore
  ]
]
```

**Parse in JavaScript:**
```javascript
const [
    openTime,
    open,
    high,
    low,
    close,
    volume,
    closeTime,
    quoteVolume,
    tradesCount
] = klineData[0];
```

---

## 🔧 Common Tasks

### Check if Klines are Being Created

```bash
php artisan tinker
```

```php
// Check klines count
Kline::count();

// Get latest klines
Kline::orderBy('open_time', 'desc')->limit(5)->get();

// Check specific symbol
Kline::where('symbol', 'BTC/USDT')->count();
```

### Manually Trigger Kline Update

```php
use App\Services\KlineService;
use App\Models\Trade;

$klineService = app(KlineService::class);
$trade = Trade::latest()->first();

$klineService->updateAllIntervalsFromTrade($trade);
```

### Fill Historical Gaps

If you have historical trades but missing klines:

```bash
php artisan klines:fill-gaps "BTC/USDT" 1m --start=1752000000 --end=1752043600
```

### Close Expired Klines

Manually close klines (normally runs automatically every minute):

```bash
php artisan klines:close-expired
```

---

## 🐛 Troubleshooting

### Problem: Klines not updating after trade

**Solution:**
```bash
# 1. Check queue is running
ps aux | grep "queue:work"

# 2. If not running, start it
php artisan queue:work

# 3. Check failed jobs
php artisan queue:failed

# 4. Retry failed jobs
php artisan queue:retry all
```

### Problem: API returns empty array

**Causes:**
1. No trades executed yet
2. Wrong symbol format
3. Time range filter too restrictive

**Check:**
```bash
# Check if trades exist
curl "http://localhost:8000/api/trades/BTC/USDT"

# Try without filters
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m"

# Check symbol format (use slash or no slash consistently)
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m"
curl "http://localhost:8000/api/klines/BTCUSDT?interval=1m"
```

### Problem: WebSocket not receiving updates

**Solution:**
```bash
# 1. Check Reverb is running
php artisan reverb:start

# 2. Check .env configuration
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# 3. Check frontend Echo configuration
# See resources/js/app.ts
```

---

## 📈 Integration with TradingView

Example of integrating klines with TradingView charts:

```javascript
import { createChart } from 'lightweight-charts';

const chart = createChart(document.getElementById('chart'), {
    width: 800,
    height: 400,
});

const candlestickSeries = chart.addCandlestickSeries();

// Fetch klines
fetch('/api/klines/BTC/USDT?interval=1h&limit=100')
    .then(res => res.json())
    .then(data => {
        const candles = data.map(k => ({
            time: k[0],          // open_time
            open: parseFloat(k[1]),
            high: parseFloat(k[2]),
            low: parseFloat(k[3]),
            close: parseFloat(k[4]),
        }));
        
        candlestickSeries.setData(candles);
    });

// Subscribe to real-time updates
echo.channel('kline.BTC-USDT.1h')
    .listen('.kline.updated', (event) => {
        candlestickSeries.update({
            time: event.k.t / 1000,  // Convert ms to seconds
            open: parseFloat(event.k.o),
            high: parseFloat(event.k.h),
            low: parseFloat(event.k.l),
            close: parseFloat(event.k.c),
        });
    });
```

---

## 🎯 Next Steps

1. **Add caching** for frequently accessed klines
2. **Implement time-series database** for better performance
3. **Add more intervals** if needed (2m, 10m, etc.)
4. **Create aggregated statistics** (24h volume, price change, etc.)
5. **Build chart UI** using TradingView or Chart.js

For detailed documentation, see [KLINE_SYSTEM.md](./KLINE_SYSTEM.md)
