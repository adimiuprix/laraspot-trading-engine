# 📊 Kline System - 1-Minute Interval

Sistem OHLCV (Candlestick) real-time untuk Scavence Exchange.

**Status:** ✅ Production Ready (1-minute interval only)

## 🎯 Apa yang Sudah Dibuat?

Sistem ini **otomatis membuat candlestick data** dari setiap trade yang terjadi:

```
Trade Execute → Kline 1m Dibuat/Update → WebSocket Broadcast
```

## 🚀 Quick Start

### 1. Migration (sudah dilakukan)
```bash
php artisan migrate
```

### 2. Start Queue Worker (WAJIB!)
```bash
php artisan queue:work
```

> ⚠️ **PENTING:** Queue worker harus running agar klines ter-update!

### 3. Test dengan Seed Data
```bash
php artisan db:seed --class=KlineTestSeeder
```

### 4. Lihat Hasil
```bash
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m&limit=10"
```

## 📡 API Endpoint

```
GET /api/klines/{symbol}?interval=1m&limit={limit}
```

**Parameters:**
- `interval`: Harus `1m` (saat ini hanya 1 menit)
- `startTime`: Unix timestamp (optional)
- `endTime`: Unix timestamp (optional)
- `limit`: Default 500, max 1000

**Response Format:**
```json
[
  [
    1752043500,          // Open time (detik)
    "50000.00000000",    // Open
    "50500.00000000",    // High
    "49800.00000000",    // Low
    "50200.00000000",    // Close
    "1.50000000",        // Volume (BTC)
    1752043559,          // Close time (detik)
    "75300.00000000",    // Quote Volume (USDT)
    10,                  // Jumlah trades
    "0", "0", "0"        // Reserved
  ]
]
```

## 🎨 WebSocket Real-time

```javascript
import { echo } from '@laravel/echo-vue';

echo.channel('kline.BTC-USDT.1m')
    .listen('.kline.updated', (event) => {
        console.log('Kline updated:', event.k);
        // {
        //   t: 1752043500,    // Open time
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

## 🔍 Cara Kerja

### 1. Bucket Time Calculation
Setiap trade di-group ke bucket 1 menit:

```php
bucket = floor(timestamp / 60) * 60

Contoh:
timestamp 10:30:17 → bucket 10:30:00
timestamp 10:30:42 → bucket 10:30:00  (same bucket)
timestamp 10:31:05 → bucket 10:31:00  (new bucket)
```

### 2. OHLCV Formation

**Trade Pertama (Candle Baru):**
```
Open  = price
High  = price
Low   = price
Close = price
Volume = quantity
```

**Trade Berikutnya (Update Candle):**
```
Open  = TIDAK BERUBAH
High  = max(high, price)
Low   = min(low, price)
Close = price (selalu update ke harga terakhir)
Volume = volume + quantity
```

### 3. Flow Lengkap

```
┌─────────────────────────────────────────────────┐
│ 1. User place order                             │
│    ↓                                             │
│ 2. Order matched                                │
│    ↓                                             │
│ 3. Trade created                                │
│    ↓                                             │
│ 4. TradeExecuted event fired                    │
│    ↓                                             │
│ 5. UpdateKlineOnTradeExecuted listener (QUEUE) │
│    ↓                                             │
│ 6. KlineService::updateOrCreateFromTrade()     │
│    ├─ Calculate bucket time                     │
│    ├─ Find existing kline OR create new         │
│    ├─ Update OHLCV values                       │
│    └─ Broadcast KlineUpdated event              │
│    ↓                                             │
│ 7. Frontend receives WebSocket update          │
└─────────────────────────────────────────────────┘
```

## 🛠️ Artisan Commands

### Close Expired Klines
```bash
php artisan klines:close-expired
```
Auto-run setiap menit via scheduler.

### Fill Historical Gaps
```bash
php artisan klines:fill-gaps "BTC/USDT" 1m --start=1752000000 --end=1752043600
```

## 🧪 Testing

```bash
# Run all tests
php artisan test --filter=KlineTest

# Should see:
# ✓ kline is created when first trade occurs
# ✓ bucket time calculation is correct
# ✓ kline high is updated when price increases
# ✓ kline low is updated when price decreases
# ✓ different intervals create different klines
# ✓ kline api endpoint returns correct format
# ✓ kline api validates interval parameter
# ✓ fill gaps creates empty klines
```

## 📚 Dokumentasi Lengkap

- **Quick Start:** [KLINE_QUICKSTART.md](./docs/KLINE_QUICKSTART.md)
- **Full Documentation:** [KLINE_SYSTEM.md](./docs/KLINE_SYSTEM.md)
- **Add More Intervals:** [HOW_TO_ADD_INTERVALS.md](./docs/HOW_TO_ADD_INTERVALS.md)

## 🔮 Future: Menambah Interval Lain

Saat ini hanya `1m` yang aktif. Untuk menambah interval lain (5m, 1h, 1d):

1. Edit `config/trading.php` - uncomment interval yang diinginkan
2. Edit `app/Http/Controllers/Api/KlineController.php` - tambahkan ke validation
3. Restart queue worker

**Detail:** Lihat [HOW_TO_ADD_INTERVALS.md](./docs/HOW_TO_ADD_INTERVALS.md)

## ⚠️ Troubleshooting

### Klines tidak ter-update setelah trade?

```bash
# 1. Pastikan queue worker running
php artisan queue:work

# 2. Check logs
tail -f storage/logs/laravel.log

# 3. Check failed jobs
php artisan queue:failed
```

### API return empty array?

```bash
# 1. Check apakah ada trades
curl "http://localhost:8000/api/trades/BTC/USDT"

# 2. Check klines di database
php artisan tinker
>>> Kline::where('symbol', 'BTC/USDT')->count();
>>> Kline::latest()->first();
```

### WebSocket tidak connect?

```bash
# 1. Start Reverb
php artisan reverb:start

# 2. Check .env
BROADCAST_CONNECTION=reverb
REVERB_HOST=localhost
REVERB_PORT=8080
```

## 🎉 Status: Production Ready

- ✅ Database migrated
- ✅ Models & Services implemented
- ✅ Event-driven architecture
- ✅ Queue processing
- ✅ REST API endpoint
- ✅ WebSocket broadcasting
- ✅ Comprehensive tests (8 tests, 49 assertions)
- ✅ Documentation complete

**Ready untuk production use dengan interval 1 menit!**

---

**Questions?** Check dokumentasi lengkap di folder `docs/`
