# 🧪 Kline System - Practical Testing Guide

Panduan testing praktis untuk memastikan sistem Kline bekerja dengan benar.

## 🎯 Pre-requisites

```bash
# 1. Migration sudah running
php artisan migrate

# 2. Queue worker HARUS running
php artisan queue:work
# JANGAN close terminal ini!
```

---

## 📋 Test Scenario 1: Auto-Generated Klines

### Step 1: Seed Test Data

```bash
php artisan db:seed --class=KlineTestSeeder
```

**Expected Output:**
```
Created test users with balances
Placing sell orders...
Placed sell order #1
Placed sell order #2
Placed sell order #3
Placing buy orders to trigger matches...
Placed buy order #1 - should match
Placed buy order #2 - should match
Placed buy order #3 - should match

✓ Test data seeded successfully!
Run: php artisan queue:work to process kline updates
Then check: GET /api/klines/BTC/USDT?interval=1m
```

### Step 2: Wait for Queue Processing

Check queue worker terminal, should see logs like:
```
[2026-01-09 10:30:15] Processing: App\Listeners\UpdateKlineOnTradeExecuted
[2026-01-09 10:30:15] Processed:  App\Listeners\UpdateKlineOnTradeExecuted
```

### Step 3: Check Klines via API

```bash
curl "http://localhost:8000/api/klines/BTC/USDT?interval=1m&limit=10"
```

**Expected Response:**
```json
[
  [
    1752043500,
    "49900.00000000",
    "50100.00000000",
    "49900.00000000",
    "50100.00000000",
    "0.45000000",
    1752043559,
    "22522.50000000",
    3,
    "0", "0", "0"
  ]
]
```

### Step 4: Verify in Database

```bash
php artisan tinker
```

```php
// Check total klines
Kline::count();
// Expected: 1 atau lebih (tergantung berapa menit yang sudah lewat)

// Check specific symbol
Kline::where('symbol', 'BTC/USDT')->count();

// Get latest kline
$kline = Kline::latest('open_time')->first();
echo "Symbol: {$kline->symbol}\n";
echo "Interval: {$kline->interval}\n";
echo "Open: {$kline->open}\n";
echo "High: {$kline->high}\n";
echo "Low: {$kline->low}\n";
echo "Close: {$kline->close}\n";
echo "Volume: {$kline->volume}\n";
echo "Trades: {$kline->trades_count}\n";
```

---

## 📋 Test Scenario 2: Manual Trade → Kline

### Step 1: Check Current Timestamp

```bash
php artisan tinker
```

```php
echo now()->timestamp; // e.g., 1752043517
echo now()->format('Y-m-d H:i:s'); // e.g., 2026-01-09 10:30:17
```

### Step 2: Create Users & Balances

```php
$buyer = User::factory()->create(['email' => 'manual-buyer@test.com']);
$seller = User::factory()->create(['email' => 'manual-seller@test.com']);

$assetService = app(\App\Services\AssetService::class);

// Give buyer USDT
$buyerUsdt = $assetService->getOrCreateAsset($buyer, 'USDT');
$buyerUsdt->quantity = '100000.00000000';
$buyerUsdt->save();

// Give seller BTC
$sellerBtc = $assetService->getOrCreateAsset($seller, 'BTC');
$sellerBtc->quantity = '10.00000000';
$sellerBtc->save();

echo "✓ Users created\n";
```

### Step 3: Place Orders

```php
use App\Services\Trading\OrderService;
use App\DTOs\PlaceLimitOrderDTO;

$orderService = app(OrderService::class);

// Sell order
$sellOrder = $orderService->placeOrder(new PlaceLimitOrderDTO(
    user: $seller,
    symbol: 'BTC/USDT',
    side: 'sell',
    price: '50000.00000000',
    quantity: '0.10000000'
));

echo "✓ Sell order placed: #{$sellOrder->id}\n";

// Buy order (will match)
$buyOrder = $orderService->placeOrder(new PlaceLimitOrderDTO(
    user: $buyer,
    symbol: 'BTC/USDT',
    side: 'buy',
    price: '50000.00000000',
    quantity: '0.10000000'
));

echo "✓ Buy order placed: #{$buyOrder->id}\n";
echo "✓ Should have matched and created a trade\n";
```

### Step 4: Check Trade Created

```php
$trade = \App\Models\Trade::latest()->first();
echo "Trade ID: {$trade->id}\n";
echo "Price: {$trade->price}\n";
echo "quantity: {$trade->quantity}\n";
echo "Created at: {$trade->created_at}\n";
```

### Step 5: Wait & Check Kline

```php
// Wait 2-3 seconds for queue processing
sleep(3);

// Calculate expected bucket
$timestamp = $trade->created_at->timestamp;
$bucket = floor($timestamp / 60) * 60;
echo "Trade timestamp: {$timestamp}\n";
echo "Expected bucket: {$bucket}\n";

// Find kline
$kline = Kline::where('symbol', 'BTC/USDT')
    ->where('interval', '1m')
    ->where('open_time', $bucket)
    ->first();

if ($kline) {
    echo "✓ Kline found!\n";
    echo "Open: {$kline->open}\n";
    echo "High: {$kline->high}\n";
    echo "Low: {$kline->low}\n";
    echo "Close: {$kline->close}\n";
    echo "Volume: {$kline->volume}\n";
    echo "Trades count: {$kline->trades_count}\n";
} else {
    echo "✗ Kline NOT found. Check queue worker!\n";
}
```

---

## 📋 Test Scenario 3: Multiple Trades in Same Minute

### Goal
Test bahwa multiple trades dalam 1 menit ter-aggregate dengan benar.

```php
$buyer = User::where('email', 'manual-buyer@test.com')->first();
$seller = User::where('email', 'manual-seller@test.com')->first();
$orderService = app(\App\Services\Trading\OrderService::class);

// Place 3 sell orders
for ($i = 1; $i <= 3; $i++) {
    $sellOrder = $orderService->placeOrder(new PlaceLimitOrderDTO(
        user: $seller,
        symbol: 'BTC/USDT',
        side: 'sell',
        price: (50000 + ($i * 100)) . '.00000000',
        quantity: '0.05000000'
    ));
    echo "Sell order #{$sellOrder->id} placed @ " . $sellOrder->price . "\n";
}

// Place 1 big buy order to match all 3
$buyOrder = $orderService->placeOrder(new PlaceLimitOrderDTO(
    user: $buyer,
    symbol: 'BTC/USDT',
    side: 'buy',
    price: '51000.00000000',
    quantity: '0.15000000'
));

echo "Buy order #{$buyOrder->id} placed\n";
echo "Should match all 3 sells\n";

// Wait for queue
sleep(3);

// Check kline
$timestamp = now()->timestamp;
$bucket = floor($timestamp / 60) * 60;

$kline = Kline::where('symbol', 'BTC/USDT')
    ->where('interval', '1m')
    ->where('open_time', $bucket)
    ->first();

if ($kline) {
    echo "\n✓ Kline with multiple trades:\n";
    echo "Open: {$kline->open} (first trade price)\n";
    echo "High: {$kline->high} (highest price)\n";
    echo "Low: {$kline->low} (lowest price)\n";
    echo "Close: {$kline->close} (last trade price)\n";
    echo "Volume: {$kline->volume} (sum of all quantitys)\n";
    echo "Trades count: {$kline->trades_count} (should be 3)\n";
}
```

**Expected:**
- `open` = first trade price (e.g., 50100)
- `high` = highest among 3 trades (e.g., 50300)
- `low` = lowest among 3 trades (e.g., 50100)
- `close` = last trade price (e.g., 50300)
- `volume` = 0.05 + 0.05 + 0.05 = 0.15
- `trades_count` = 3

---

## 📋 Test Scenario 4: WebSocket Real-time

### Backend Setup

```bash
# Terminal 1: Queue worker
php artisan queue:work

# Terminal 2: Reverb
php artisan reverb:start

# Terminal 3: App server
php artisan serve
```

### Frontend Test (Browser Console)

```javascript
// Open browser console di http://localhost:8000
// Paste this code:

import { echo } from '@laravel/echo-vue';

echo.channel('kline.BTC-USDT.1m')
    .listen('.kline.updated', (event) => {
        console.log('📊 Kline Updated!');
        console.table({
            'Time': new Date(event.k.t).toLocaleString(),
            'Symbol': event.k.s,
            'Interval': event.k.i,
            'Open': event.k.o,
            'High': event.k.h,
            'Low': event.k.l,
            'Close': event.k.c,
            'Volume': event.k.v,
            'Trades': event.k.n,
            'Closed': event.k.x ? 'Yes' : 'No'
        });
    });

console.log('✓ Subscribed to kline.BTC-USDT.1m');
console.log('Now place a trade and watch for updates!');
```

### Trigger Trade

```bash
# In another terminal
php artisan tinker
```

```php
$buyer = User::where('email', 'manual-buyer@test.com')->first();
$seller = User::where('email', 'manual-seller@test.com')->first();
$orderService = app(\App\Services\Trading\OrderService::class);

// Ensure they have balance
$assetService = app(\App\Services\AssetService::class);
$buyerUsdt = $assetService->getOrCreateAsset($buyer, 'USDT');
$buyerUsdt->quantity = '100000.00000000';
$buyerUsdt->save();

$sellerBtc = $assetService->getOrCreateAsset($seller, 'BTC');
$sellerBtc->quantity = '10.00000000';
$sellerBtc->save();

// Place matching orders
$sellOrder = $orderService->placeOrder(new PlaceLimitOrderDTO(
    user: $seller,
    symbol: 'BTC/USDT',
    side: 'sell',
    price: '55000.00000000',
    quantity: '0.05000000'
));

$buyOrder = $orderService->placeOrder(new PlaceLimitOrderDTO(
    user: $buyer,
    symbol: 'BTC/USDT',
    side: 'buy',
    price: '55000.00000000',
    quantity: '0.05000000'
));

echo "✓ Orders placed. Check browser console for WebSocket event!\n";
```

**Expected in Browser Console:**
You should see a table with updated kline data within 1-2 seconds.

---

## 📋 Test Scenario 5: Gap Filling

### Create Gap Scenario

```php
// Create a kline at 10:00
Kline::create([
    'symbol' => 'BTC/USDT',
    'interval' => '1m',
    'open_time' => 600,  // 10:00
    'close_time' => 659,
    'open' => '50000.00000000',
    'high' => '50000.00000000',
    'low' => '50000.00000000',
    'close' => '50200.00000000',
    'volume' => '1.00000000',
    'quote_volume' => '50200.00000000',
    'trades_count' => 1,
    'is_closed' => true,
]);

// No kline for 10:01, 10:02, 10:03...
// Create a kline at 10:05
Kline::create([
    'symbol' => 'BTC/USDT',
    'interval' => '1m',
    'open_time' => 900,  // 10:05
    'close_time' => 959,
    'open' => '50300.00000000',
    'high' => '50300.00000000',
    'low' => '50300.00000000',
    'close' => '50400.00000000',
    'volume' => '1.00000000',
    'quote_volume' => '50400.00000000',
    'trades_count' => 1,
    'is_closed' => true,
]);

echo "✓ Created klines with gaps\n";
```

### Fill Gaps

```bash
php artisan klines:fill-gaps "BTC/USDT" 1m --start=600 --end=900
```

**Expected Output:**
```
Filling gaps for BTC/USDT (1m)...
Time range: 600 to 900
Filled 4 gap(s).
```

### Verify

```php
// Check klines now exist
$klines = Kline::where('symbol', 'BTC/USDT')
    ->where('interval', '1m')
    ->where('open_time', '>=', 600)
    ->where('open_time', '<=', 900)
    ->orderBy('open_time')
    ->get();

foreach ($klines as $kline) {
    echo "Time: {$kline->open_time} | ";
    echo "OHLC: {$kline->open}/{$kline->high}/{$kline->low}/{$kline->close} | ";
    echo "Volume: {$kline->volume} | ";
    echo "Trades: {$kline->trades_count}\n";
}
```

**Expected:**
- Gap klines have OHLC = previous close
- Gap klines have volume = 0
- Gap klines have trades_count = 0

---

## ✅ Success Checklist

After running all scenarios, you should have:

- ✅ Klines automatically created from trades
- ✅ Multiple trades in same minute properly aggregated
- ✅ OHLCV values calculated correctly:
  - Open = first trade price (never changes)
  - High = maximum price
  - Low = minimum price
  - Close = last trade price
  - Volume = sum of all quantitys
- ✅ WebSocket broadcasting working
- ✅ Gap filling working
- ✅ API endpoint returning correct format

---

## 🐛 Common Issues

### Issue: Klines not created

**Check:**
```bash
# 1. Queue worker running?
ps aux | grep "queue:work"

# 2. Any errors?
tail -f storage/logs/laravel.log

# 3. Failed jobs?
php artisan queue:failed
```

### Issue: WebSocket not working

**Check:**
```bash
# 1. Reverb running?
ps aux | grep "reverb"

# 2. Check .env
cat .env | grep BROADCAST
cat .env | grep REVERB
```

### Issue: Wrong OHLCV values

**Debug:**
```php
// Get kline with trades
$kline = Kline::with(['trades'])->latest('open_time')->first();

// Manually calculate
$trades = Trade::whereHas('buyerOrder', fn($q) => 
    $q->where('symbol', $kline->symbol)
)->whereBetween('created_at', [
    $kline->open_time,
    $kline->close_time
])->get();

echo "Trades count: " . $trades->count() . "\n";
echo "Should match kline trades_count: {$kline->trades_count}\n";
```

---

## 🎉 Next Steps

Setelah semua test pass:

1. **Monitor Production:**
   - Setup queue monitoring
   - Setup error alerting
   - Monitor kline creation rate

2. **Add More Intervals:**
   - See [HOW_TO_ADD_INTERVALS.md](./HOW_TO_ADD_INTERVALS.md)

3. **Build Charts:**
   - Integrate with TradingView Lightweight Charts
   - Or use Chart.js for candlestick visualization

4. **Optimize:**
   - Add caching for frequently accessed klines
   - Consider time-series database for historical data
