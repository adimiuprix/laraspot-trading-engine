# 🚀 Test Real-time Kline dengan Reverb

## Setup

### Terminal 1: Queue Worker
```bash
php artisan queue:work --verbose
```

### Terminal 2: Reverb WebSocket Server
```bash
php artisan reverb:start --debug
```

### Terminal 3: App Server
```bash
php artisan serve
```

---

## Test Scenario

### Step 1: Open Browser Console

Buka http://localhost:8000 di browser, tekan F12, masuk ke Console tab.

### Step 2: Subscribe to Kline Channel

Paste code ini di console:

```javascript
// Import Echo (assuming sudah configured di app.ts)
import { echo } from '@laravel/echo-vue';

// Subscribe to BTC/USDT 1-minute klines
console.log('🎧 Subscribing to kline.BTC-USDT.1m...');

echo.channel('kline.BTC-USDT.1m')
    .listen('.kline.updated', (event) => {
        console.log('🔥 KLINE UPDATED!');
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log('Symbol:', event.s);
        console.log('Interval:', event.k.i);
        console.log('Open Time:', new Date(event.k.t).toLocaleString());
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.table({
            'Open': event.k.o,
            'High': event.k.h,
            'Low': event.k.l,
            'Close': event.k.c,
            'Volume': event.k.v,
            'Trades': event.k.n,
            'Closed': event.k.x ? '✅' : '⏳'
        });
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');
    })
    .subscribed(() => {
        console.log('✅ Successfully subscribed to kline.BTC-USDT.1m');
    })
    .error((error) => {
        console.error('❌ Subscription error:', error);
    });
```

### Step 3: Trigger Trade (Terminal 4)

```bash
php artisan tinker
```

```php
// Create users if not exist
$buyer = \App\Models\User::where('email', 'test-buyer@test.com')->first();
$seller = \App\Models\User::where('email', 'test-seller@test.com')->first();

if (!$buyer) {
    $buyer = \App\Models\User::factory()->create(['email' => 'test-buyer@test.com']);
    $seller = \App\Models\User::factory()->create(['email' => 'test-seller@test.com']);
    
    // Give balances
    $assetService = app(\App\Services\AssetService::class);
    
    $buyerUsdt = $assetService->getOrCreateAsset($buyer, 'USDT');
    $buyerUsdt->amount = '100000.00000000';
    $buyerUsdt->save();
    
    $sellerBtc = $assetService->getOrCreateAsset($seller, 'BTC');
    $sellerBtc->amount = '10.00000000';
    $sellerBtc->save();
    
    echo "✓ Users created with balances\n";
}

// Place orders
$orderService = app(\App\Services\Trading\OrderService::class);

// Sell order
$sellOrder = $orderService->placeOrder(new \App\DTOs\PlaceLimitOrderDTO(
    user: $seller,
    symbol: 'BTC/USDT',
    side: 'sell',
    price: '50000.00000000',
    amount: '0.01000000'
));

echo "✓ Sell order placed: #{$sellOrder->id}\n";

// Buy order (will match and create trade)
$buyOrder = $orderService->placeOrder(new \App\DTOs\PlaceLimitOrderDTO(
    user: $buyer,
    symbol: 'BTC/USDT',
    side: 'buy',
    price: '50000.00000000',
    amount: '0.01000000'
));

echo "✓ Buy order placed: #{$buyOrder->id}\n";
echo "✓ Trade executed!\n";
echo "\n👀 CHECK YOUR BROWSER CONSOLE NOW!\n";
echo "You should see kline update within 1-2 seconds\n";
```

---

## Expected Results

### Terminal 1 (Queue Worker):
```
[2026-01-09 15:30:45] Processing: App\Listeners\UpdateKlineOnTradeExecuted
[2026-01-09 15:30:45] Processing kline update from trade
[2026-01-09 15:30:45] Created new kline
[2026-01-09 15:30:45] Kline update completed
[2026-01-09 15:30:45] Processed:  App\Listeners\UpdateKlineOnTradeExecuted
```

### Terminal 2 (Reverb):
```
[2026-01-09 15:30:45] Broadcasting to channel kline.BTC-USDT.1m
[2026-01-09 15:30:45] Event: kline.updated
[2026-01-09 15:30:45] Clients notified: 1
```

### Browser Console:
```
🔥 KLINE UPDATED!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Symbol: BTC/USDT
Interval: 1m
Open Time: 1/9/2026, 3:30:00 PM
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
┌─────────┬──────────────────┐
│ (index) │      Values      │
├─────────┼──────────────────┤
│  Open   │ '50000.00000000' │
│  High   │ '50000.00000000' │
│   Low   │ '50000.00000000' │
│  Close  │ '50000.00000000' │
│ Volume  │ '0.01000000'     │
│ Trades  │        1         │
│ Closed  │       '⏳'        │
└─────────┴──────────────────┘
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

## Multiple Trades Test

Place multiple trades in same minute:

```php
// Continue in tinker...

for ($i = 1; $i <= 3; $i++) {
    $price = (50000 + ($i * 10)) . '.00000000';
    
    $sellOrder = $orderService->placeOrder(new \App\DTOs\PlaceLimitOrderDTO(
        user: $seller,
        symbol: 'BTC/USDT',
        side: 'sell',
        price: $price,
        amount: '0.01000000'
    ));
    
    $buyOrder = $orderService->placeOrder(new \App\DTOs\PlaceLimitOrderDTO(
        user: $buyer,
        symbol: 'BTC/USDT',
        side: 'buy',
        price: $price,
        amount: '0.01000000'
    ));
    
    echo "✓ Trade #{$i} at {$price}\n";
    sleep(1); // Wait 1 second between trades
}

echo "\n👀 CHECK BROWSER: You should see 3 kline updates!\n";
```

**Expected in Browser:**
You'll see **3 separate updates**, each showing:
- `Open` = 50000 (first trade, never changes)
- `High` = increases (50000 → 50010 → 50020 → 50030)
- `Low` = stays 50000 (lowest)
- `Close` = latest trade price (50010 → 50020 → 50030)
- `Volume` = accumulates (0.01 → 0.02 → 0.03 → 0.04)
- `Trades` = increments (1 → 2 → 3 → 4)

---

## Troubleshooting

### No update in browser?

**Check 1: Queue worker running?**
```bash
ps aux | grep "queue:work"
```

**Check 2: Reverb running?**
```bash
ps aux | grep "reverb"
```

**Check 3: Browser subscribed?**
```javascript
// Check Echo is connected
echo.connector.pusher.connection.state
// Should be: "connected"
```

**Check 4: Check logs**
```bash
tail -f storage/logs/laravel.log
```

### Update delay?

Normal delay: **1-3 seconds** karena:
1. Trade execute (instant)
2. Queue worker picks up job (~1s)
3. Kline update (~0.5s)
4. Broadcast via Reverb (~0.5s)
5. Frontend receives (~0.5s)

Total: ~2-3 seconds dari trade execute ke chart update.

---

## Success Criteria

✅ Browser console shows kline update within 3 seconds  
✅ Queue worker logs show "Processing" and "Processed"  
✅ Reverb logs show "Broadcasting to channel"  
✅ OHLCV values calculated correctly  
✅ Multiple trades update same kline  
✅ No errors in any terminal  

---

## Conclusion

Jika semua criteria terpenuhi, maka **sistem kline real-time sudah working 100%!** 🎉

Setiap match order akan:
1. Create trade
2. Auto-update kline (via queue)
3. Broadcast via Reverb
4. Frontend chart update instantly

**Real-time trading experience!** 🚀📊
