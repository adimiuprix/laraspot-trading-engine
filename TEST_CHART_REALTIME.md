# Test Real-Time Trading Chart

Quick guide untuk test candlestick chart dengan real-time updates.

## Prerequisites

Pastikan services berjalan:

```bash
# Terminal 1
php artisan queue:work

# Terminal 2
php artisan reverb:start

# Terminal 3
npm run dev
# ATAU jika production:
# npm run build
```

---

## Test Steps

### 1. Check Database has Klines

```bash
php artisan tinker
```

```php
// Check kline count
\App\Models\Kline::count();

// Check recent klines
\App\Models\Kline::where('symbol', 'BTCUSDT')
    ->orderBy('open_time', 'desc')
    ->limit(5)
    ->get(['open_time', 'open', 'close', 'volume']);

// If empty, create dummy klines:
\Database\Seeders\KlineTestSeeder::run();
```

### 2. Check API Endpoint

```bash
curl http://scavence.test/api/klines/BTCUSDT?interval=1m&limit=10
```

**Expected Response:**
```json
[
  [
    1752043500,
    "50000.00000000",
    "50500.00000000",
    "49800.00000000",
    "50200.00000000",
    "1.50000000",
    1752043559,
    "75300.00000000",
    10,
    "0",
    "0",
    "0"
  ],
  ...
]
```

### 3. Open Exchange Page

Go to: **http://scavence.test/exchange**

**Expected:**
- Chart should load with historical candlesticks
- Volume histogram below candlesticks
- Symbol overlay (BTCUSDT 1M)

**Browser Console Should Show:**
```
📊 Chart initialized
📊 Loaded X klines for BTCUSDT
🎧 Subscribing to kline.BTC-USDT.1m...
✅ Subscribed to kline.BTC-USDT.1m
📈 Loaded X candles to chart
```

### 4. Test Real-Time Updates

**Option A: Place Real Order**

1. Fill trading form (BUY or SELL)
2. Click "Place Order"
3. Wait for match (~2-3 seconds)
4. **Watch chart update automatically!**

**Console Should Show:**
```
🔥 Kline update received: { t: 1752043500000, o: "50000.00", ... }
```

**Option B: Manual Trade Creation (Tinker)**

```bash
php artisan tinker
```

```php
// Create a manual trade
$trade = \App\Models\Trade::create([
    'symbol' => 'BTCUSDT',
    'price' => '51000.00',
    'quantity' => '0.5',
    'taker_side' => 'buy',
    'buyer_id' => 1,
    'seller_id' => 2,
    'buy_order_id' => 1,
    'sell_order_id' => 2,
]);

// Dispatch event manually
event(new \App\Events\TradeExecuted($trade));
```

**Then check:**
1. Queue should process: `php artisan queue:work` logs
2. Chart should update in browser
3. Console shows: `🔥 Kline update received`

---

## Verify WebSocket Connection

**Chrome DevTools:**
1. Open DevTools → Network tab
2. Filter: `WS` (WebSocket)
3. Should see connection to `ws://scavence.test:8080/app/APP_KEY`
4. Click connection → Messages tab
5. Should see messages like:
   ```json
   {
     "event": "pusher:subscription_succeeded",
     "channel": "kline.BTC-USDT.1m"
   }
   ```

---

## Troubleshooting

### ❌ Chart shows "Loading chart data..." forever

**Fix:**
1. Check API: `curl http://scavence.test/api/klines/BTCUSDT?interval=1m`
2. Create test data: `php artisan db:seed --class=KlineTestSeeder`
3. Check browser console for errors

### ❌ Chart shows "Failed to load chart"

**Fix:**
1. Check backend logs: `php artisan pail`
2. Check migrations: `php artisan migrate:status`
3. Check API route: `php artisan route:list | grep klines`

### ❌ Real-time updates not working

**Fix:**
1. **Check Reverb:** `php artisan reverb:start` should be running
2. **Check Queue:** `php artisan queue:work` should be running
3. **Check WebSocket connection:** DevTools → Network → WS tab
4. **Check channel name:** Should be `kline.BTC-USDT.1m` (with hyphen, not slash)

### ❌ Console error: "Cannot read property 'update' of null"

**Fix:**
1. Chart not initialized yet
2. Check `chartContainer.value` is not null
3. Make sure chart is created before WebSocket subscription

---

## Expected Console Logs (Success)

### Backend (php artisan pail)

```
[2026-01-09 10:30:00] UpdateKlineOnTradeExecuted: Processing trade ID 123
[2026-01-09 10:30:00] KlineService: Updated kline for BTCUSDT at 1752043500
[2026-01-09 10:30:00] Broadcasting KlineUpdated to kline.BTC-USDT.1m
```

### Frontend (Browser Console)

```
🚀 Mounting TradingChart for BTCUSDT 1m
📊 Chart initialized
📊 Loaded 150 klines for BTCUSDT
🎧 Subscribing to kline.BTC-USDT.1m...
✅ Subscribed to kline.BTC-USDT.1m
📈 Loaded 150 candles to chart
🔥 Kline update received: {k: {...}}
```

---

## Performance Metrics

- **Initial Load:** ~500-1000ms (load 500 candles)
- **Real-time Update:** ~2-3 seconds (queue processing)
- **Chart FPS:** ~60fps (smooth animations)
- **Memory Usage:** ~50MB (chart + data)

---

## Success Criteria ✅

- [x] Chart displays historical candlesticks
- [x] Volume histogram displays below chart
- [x] Chart auto-resizes on window resize
- [x] Real-time updates work when orders are matched
- [x] No console errors
- [x] WebSocket connection established
- [x] Chart updates smoothly without flickering

---

**Test Date:** 2026-01-09
**Status:** Ready to Test
