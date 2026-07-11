# Live Candlestick Implementation Guide

## 🎯 Overview

Sistem ini mengimplementasikan **live candlestick** yang update secara real-time saat trade terjadi, bukan membuat candle baru setiap kali ada update.

## 📊 How It Works

### **1. Trade Execution Flow**

```
Trade Executed
      ↓
TradeExecuted Event (Broadcast)
      ↓
UpdateKlineOnTradeExecuted Listener (Queued)
      ↓
KlineService::updateAllIntervalsFromTrade()
      ↓
For each interval (1m, 5m, etc):
  ↓
  Calculate bucket: floor(timestamp / interval) * interval
  ↓
  Find existing kline by bucket?
  ├─ YES: Update high/low/close/volume (LIVE CANDLE)
  └─ NO:  Create new kline (NEW CANDLE)
      ↓
KlineUpdated Event (Broadcast via WebSocket)
      ↓
Frontend receives update
      ↓
Check kline.time === lastCandle.time?
  ├─ YES: Update existing candle (live update)
  └─ NO:  Add new candle (new interval started)
```

### **2. Candle Lifecycle**

#### **State 1: Candle Created (First Trade in Interval)**
```php
// Backend
[
    'open_time' => 1752043500,
    'close_time' => 1752043559,
    'open' => '100000',
    'high' => '100000',
    'low' => '100000',
    'close' => '100000',
    'volume' => '0.5',
    'is_closed' => false  // ← Not closed yet
]
```

```typescript
// Frontend receives via WebSocket
{
    time: 1752043500,
    open: 100000,
    high: 100000,
    low: 100000,
    close: 100000,
    volume: 0.5
}
// Chart adds this as NEW candle
```

#### **State 2: Candle Updated (More Trades in Same Interval)**
```php
// Backend (same open_time = same bucket)
[
    'open_time' => 1752043500,  // ← Same time!
    'close_time' => 1752043559,
    'open' => '100000',         // Never changes
    'high' => '100200',         // Updated
    'low' => '99800',           // Updated
    'close' => '100050',        // Updated (latest trade)
    'volume' => '2.5',          // Accumulated
    'is_closed' => false        // Still open
]
```

```typescript
// Frontend receives update
{
    time: 1752043500,  // ← Same time as previous!
    open: 100000,
    high: 100200,
    low: 99800,
    close: 100050,
    volume: 2.5
}
// Chart UPDATES existing candle (because time matches)
```

#### **State 3: Candle Closed (Interval Finished)**

**Option A: Closed by scheduled command**
```bash
# Runs every minute
php artisan klines:close-expired
```

**Option B: Auto-closed when next trade arrives**
```php
// If trade timestamp > close_time, auto-close previous candle
if (!$kline->is_closed && $timestamp > $kline->close_time) {
    $kline->markAsClosed();
    event(new KlineUpdated($kline)); // Broadcast closed status
    
    // Create new kline for this trade
    return $this->updateOrCreateFromTrade(...);
}
```

```typescript
// Frontend receives closed event
{
    time: 1752043500,
    ...
    // Event includes: k.x = true (is_closed)
}
// Frontend knows: next trade will create NEW candle
```

---

## 🔑 Key Components

### **Backend**

#### **1. KlineService::updateOrCreateFromTrade()**
```php
// Located: app/Services/KlineService.php

// Purpose:
// - Calculate bucket time for trade
// - Find or create kline for that bucket
// - Update OHLCV values
// - Auto-close previous candle if needed
// - Broadcast KlineUpdated event
```

#### **2. KlineUpdated Event**
```php
// Located: app/Events/KlineUpdated.php

// Broadcasts to: kline.{symbol}.{interval}
// Example: kline.BTCUSDT.1m

// Data sent:
[
    'e' => 'kline',
    's' => 'BTCUSDT',
    'k' => [
        't' => 1752043500000,  // Open time (ms)
        'T' => 1752043559000,  // Close time (ms)
        'o' => '100000',       // Open
        'h' => '100200',       // High
        'l' => '99800',        // Low
        'c' => '100050',       // Close
        'v' => '2.5',          // Volume
        'x' => false,          // is_closed (KEY!)
    ]
]
```

#### **3. CloseExpiredKlines Command**
```php
// Located: app/Console/Commands/CloseExpiredKlines.php

// Scheduled: Every minute (routes/console.php)
// Purpose: Close candles that passed close_time and broadcast
```

### **Frontend**

#### **1. useKlines.ts Composable**
```typescript
// Located: resources/js/composables/useKlines.ts

// Purpose:
// - Fetch historical klines from API
// - Subscribe to WebSocket for live updates
// - Manage klines array state
// - Detect live update vs new candle by comparing time
```

**Live Update Detection Logic:**
```typescript
subscribeToUpdates((kline, isClosed) => {
    if (klines.value.length > 0) {
        const lastKline = klines.value[klines.value.length - 1];
        
        if (lastKline.time === updatedKline.time) {
            // LIVE UPDATE: Replace last candle
            klines.value[klines.value.length - 1] = updatedKline;
        } else {
            // NEW CANDLE: Previous was closed, add new one
            klines.value.push(updatedKline);
        }
    }
});
```

#### **2. TradingChart.vue Component**
```typescript
// Located: resources/js/components/Exchange/TradingChart.vue

// Uses: lightweight-charts library
// The library automatically handles:
// - candlestickSeries.update({ time: X, ... })
//   → If time X exists: updates existing candle
//   → If time X is new: creates new candle
```

---

## ✅ Testing Live Candle

### **Step 1: Start Development Environment**
```bash
# Terminal 1: Laravel + Queue + Vite
composer run dev

# This runs:
# - php artisan serve
# - php artisan queue:listen (for KlineUpdated listener)
# - npm run dev

# Terminal 2: Start scheduler (for auto-closing candles)
php artisan schedule:work
```

### **Step 2: Start Reverb (WebSocket Server)**
```bash
php artisan reverb:start
```

### **Step 3: Place Some Orders**
```
1. Login to /exchange
2. Place buy/sell orders
3. Watch trades execute
4. Watch chart update LIVE!
```

### **What to Observe:**

✅ **Live Update (Same Minute):**
```
10:00:05 - Trade at 100000 → New candle created
10:00:15 - Trade at 100050 → Candle HIGH updated
10:00:30 - Trade at 99980  → Candle LOW updated
10:00:45 - Trade at 100100 → Candle HIGH & CLOSE updated
10:00:58 - Trade at 100020 → Candle CLOSE updated

Result: Only ONE candle for 10:00:00 - 10:00:59
```

✅ **New Candle (Next Minute):**
```
10:01:05 - Trade at 100030 → NEW candle created for 10:01:00
Previous candle (10:00:00) is now closed
```

### **Console Logs to Check:**

**Backend:**
```
✨ Created new kline [symbol: BTCUSDT, interval: 1m, open_time: 1752043500]
📊 Updated live kline [symbol: BTCUSDT, price: 100050, trades_count: 2]
📊 Updated live kline [symbol: BTCUSDT, price: 99980, trades_count: 3]
🔒 Auto-closed previous kline (trade arrived late)
```

**Frontend:**
```
📊 Loaded 500 klines for BTCUSDT
🎧 Subscribing to kline.BTCUSDT.1m...
🔥 Kline UPDATE: { time: 1752043500, close: 100050, isClosed: false }
🔥 Kline UPDATE: { time: 1752043500, close: 99980, isClosed: false }
🔥 Kline CLOSED: { time: 1752043500, close: 100020, isClosed: true }
✅ Candle closed, next trade will create new candle
```

---

## 🔧 Configuration

### **Kline Intervals**
```php
// config/trading.php

'kline_intervals' => [
    '1m' => 60,      // 1 minute (active)
    // '5m' => 300,  // 5 minutes (disabled)
    // '1h' => 3600, // 1 hour (disabled)
],
```

### **WebSocket Channels**
```php
// app/Events/KlineUpdated.php

// Channel format: kline.{SYMBOL}.{INTERVAL}
// Example: kline.BTCUSDT.1m

// Frontend subscribes to same channel
// resources/js/composables/useKlines.ts
```

---

## 🚀 Performance Optimizations

### **1. Queued Listener**
```php
// app/Listeners/UpdateKlineOnTradeExecuted.php
implements ShouldQueue

// Benefits:
// ✅ Trade execution not blocked by kline updates
// ✅ Retry on failure (max 3 attempts)
// ✅ Process multiple intervals asynchronously
```

### **2. Debounced Frontend Refresh**
```typescript
// Frontend only updates chart, not re-fetching API
// WebSocket provides instant updates
```

### **3. Memory Management**
```typescript
// Keep only last 500 candles in memory
if (klines.value.length > 500) {
    klines.value.shift();
}
```

---

## 📈 Future Enhancements

### **Multiple Intervals**
Currently only 1m is active. To add more:

1. **Enable in config:**
```php
// config/trading.php
'kline_intervals' => [
    '1m' => 60,
    '5m' => 300,
    '15m' => 900,
    '1h' => 3600,
],
```

2. **Klines will auto-generate** for all intervals from each trade

3. **Frontend subscribes** to desired interval:
```typescript
const { klines } = useKlines('BTCUSDT', '5m');
```

### **Kline Aggregation**
Build higher timeframes from 1m klines (more efficient than recalculating from trades):
```php
// Build 5m kline from five 1m klines
// Build 1h kline from twelve 5m klines
```

---

## 🐛 Troubleshooting

### **Problem: Candle not updating**

**Check:**
1. ✅ Queue worker running? `php artisan queue:listen`
2. ✅ Reverb running? `php artisan reverb:start`
3. ✅ WebSocket connected? Check browser console
4. ✅ Channel subscribed? `Echo.channel('kline.BTCUSDT.1m')`

### **Problem: Multiple candles for same minute**

**Cause:** Frontend not detecting `time` match

**Fix:** Check `useKlines.ts` logic:
```typescript
if (lastKline.time === updatedKline.time) {
    // Should UPDATE, not ADD
}
```

### **Problem: Candle never closes**

**Cause:** Scheduler not running

**Fix:**
```bash
# Development
php artisan schedule:work

# Production
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 📚 Related Files

**Backend:**
- `app/Services/KlineService.php` - Main kline logic
- `app/Events/KlineUpdated.php` - WebSocket broadcast
- `app/Listeners/UpdateKlineOnTradeExecuted.php` - Trade → Kline
- `app/Console/Commands/CloseExpiredKlines.php` - Auto-close
- `routes/console.php` - Scheduler config

**Frontend:**
- `resources/js/composables/useKlines.ts` - Kline state management
- `resources/js/components/Exchange/TradingChart.vue` - Chart rendering

**Config:**
- `config/trading.php` - Intervals, fees, limits
- `config/broadcasting.php` - WebSocket config

---

## 🎓 Key Concepts

1. **Bucket Time** = `floor(timestamp / interval) * interval`
   - Groups trades into intervals
   - Same bucket = same candle

2. **Live Candle** = Candle with `is_closed = false`
   - Open can be updated (High, Low, Close, Volume)
   - Open price NEVER changes

3. **Closed Candle** = Candle with `is_closed = true`
   - No more updates allowed
   - Finalized historical data

4. **WebSocket Update** = Broadcast every trade
   - Frontend detects live vs new by comparing `time`
   - lightweight-charts handles the rest automatically

---

**Built with ❤️ following OHLCV_Real_Time_Guide.md principles**
