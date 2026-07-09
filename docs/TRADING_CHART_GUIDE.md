# Trading Chart Implementation Guide

## Overview

Candlestick chart dengan real-time updates menggunakan **TradingView Lightweight Charts** library.

## Architecture

```
Exchange.vue
  ├─ TradingChart.vue (chart component)
  │   └─ useKlines composable (data management)
  │       ├─ Fetch historical klines via REST API
  │       └─ Subscribe to WebSocket updates via Laravel Reverb
```

## Components

### 1. **useKlines.ts** Composable

Location: `resources/js/composables/useKlines.ts`

**Features:**
- Fetch historical klines from REST API `/api/klines/{symbol}`
- Subscribe to real-time WebSocket updates via `kline.{symbol}.{interval}` channel
- Auto-update chart data when new kline updates arrive
- Auto-unsubscribe on component unmount

**Usage:**
```typescript
const { klines, loading, error, fetchKlines, subscribeToUpdates } = useKlines('BTCUSDT', '1m');

// Fetch historical data
await fetchKlines(500);

// Subscribe to real-time updates
subscribeToUpdates((kline) => {
    console.log('New kline update:', kline);
});
```

**Data Format:**
```typescript
interface Kline {
    time: number;    // Unix timestamp (seconds)
    open: number;    // Open price
    high: number;    // High price
    low: number;     // Low price
    close: number;   // Close price
    volume: number;  // Volume
}
```

---

### 2. **TradingChart.vue** Component

Location: `resources/js/components/Exchange/TradingChart.vue`

**Features:**
- Display candlestick chart with volume histogram
- Real-time updates via WebSocket
- Responsive design (auto-resize on window resize)
- Loading and error states
- Symbol and interval overlay

**Props:**
```typescript
{
    symbol?: string;   // Default: 'BTCUSDT'
    interval?: string; // Default: '1m'
}
```

**Chart Configuration:**
- **Candlestick colors:** Green (up), Red (down)
- **Volume colors:** Semi-transparent green/red
- **Volume height:** 30% of chart height
- **Time scale:** Shows time (HH:MM)
- **Dark theme:** Background #1a202c

**Usage in Exchange.vue:**
```vue
<TradingChart :symbol="symbol" interval="1m" />
```

---

## Real-Time Flow

```
1. User places order
   ↓
2. Order matched → TradeExecuted event
   ↓
3. UpdateKlineOnTradeExecuted listener (queued)
   ↓
4. KlineService updates/creates kline
   ↓
5. KlineUpdated event → Reverb broadcast
   ↓
6. Frontend WebSocket receives update
   ↓
7. useKlines composable updates klines array
   ↓
8. TradingChart.vue calls candlestickSeries.update()
   ↓
9. Chart updates in real-time! 🎉
```

**Total Latency:** ~2-3 seconds (due to queue processing)

---

## How to Test

### 1. Start Required Services

```bash
# Terminal 1: Queue worker
php artisan queue:work

# Terminal 2: Reverb WebSocket server
php artisan reverb:start

# Terminal 3: Vite dev server
npm run dev
```

### 2. Place Orders

1. Go to http://scavence.test/exchange
2. Place BUY or SELL orders
3. Watch the chart update in real-time!

### 3. Check Logs

**Backend:**
```bash
# Check kline creation
php artisan pail --filter=UpdateKlineOnTradeExecuted

# Check broadcasting
php artisan pail --filter=KlineUpdated
```

**Frontend Console:**
- `📊 Loaded X klines for BTCUSDT` - Historical data loaded
- `🎧 Subscribing to kline.BTC-USDT.1m...` - WebSocket subscribing
- `✅ Subscribed to kline.BTC-USDT.1m` - Subscription successful
- `🔥 Kline update received` - Real-time update received
- `📈 Loaded X candles to chart` - Chart data loaded

---

## Troubleshooting

### Chart not loading?

1. **Check API:** `curl http://scavence.test/api/klines/BTCUSDT?interval=1m`
2. **Check database:** `php artisan tinker` → `\App\Models\Kline::count()`
3. **Check browser console** for errors

### Real-time updates not working?

1. **Check Reverb is running:** `php artisan reverb:start`
2. **Check queue worker is running:** `php artisan queue:work`
3. **Check WebSocket connection in Network tab:** Should see "reverb" connection
4. **Check browser console:** Should see "Subscribed to kline.BTC-USDT.1m"

### Chart looks weird?

1. **Clear cache:** `npm run build`
2. **Check chart container height:** Should be `h-full`
3. **Check parent flex container:** Should have `flex-1`

---

## Configuration

### Change Interval

Currently only **1m (1 minute)** is active. To enable other intervals:

1. Edit `config/trading.php`:
   ```php
   'kline_intervals' => [
       '1m' => 60,
       // '5m' => 300,   // Uncomment to enable
       // '15m' => 900,  // Uncomment to enable
   ]
   ```

2. Update frontend:
   ```vue
   <TradingChart :symbol="symbol" interval="5m" />
   ```

### Change Chart Colors

Edit `TradingChart.vue`:
```typescript
candlestickSeries = chart.addCandlestickSeries({
    upColor: '#10b981',    // Change green color
    downColor: '#ef4444',  // Change red color
    // ...
});
```

### Change History Limit

Default: 500 candles. To change:
```typescript
await fetchKlines(1000); // Load 1000 candles
```

---

## Libraries Used

- **lightweight-charts** (v4.x): TradingView's free charting library
- **Laravel Reverb**: WebSocket server for real-time updates
- **Laravel Echo**: WebSocket client for frontend

## Files Modified

1. `resources/js/composables/useKlines.ts` - NEW
2. `resources/js/components/Exchange/TradingChart.vue` - UPDATED
3. `resources/js/pages/Exchange.vue` - UPDATED (added symbol prop)
4. `package.json` - Added `lightweight-charts` dependency

---

## Next Steps

- [ ] Add interval selector (1m, 5m, 15m, etc.)
- [ ] Add indicators (MA, EMA, RSI, etc.)
- [ ] Add drawing tools (trend lines, etc.)
- [ ] Add full-screen mode
- [ ] Add chart settings panel
- [ ] Add price alerts

---

**Last Updated:** 2026-01-09
**Status:** ✅ Production Ready
