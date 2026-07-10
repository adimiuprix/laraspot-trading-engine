# Testing Guide - Exchange Page

## 🚀 Quick Start

### 1. Start Services

```bash
# Terminal 1: Start Reverb (WebSocket server)
php artisan reverb:start

# Terminal 2: Start Vite (Frontend dev server)
npm run dev

# Terminal 3: Start Laravel (Backend server - if using artisan serve)
php artisan serve
```

### 2. Access Application

```
URL: http://localhost/exchange
Login: trader1@example.com / password
```

---

## 🔍 Troubleshooting Empty Orderbook

### Issue: Orderbook shows "Loading orderbook..." or empty

#### **Check 1: Verify Database Has Data**

```bash
php artisan tinker
```

```php
// Check orders exist
\App\Models\Order::where('symbol', 'BTC/USDT')->where('status', 'open')->count();
// Should return 6 (3 buy orders + 3 sell orders)

// Check actual data
\App\Models\Order::where('symbol', 'BTC/USDT')->where('status', 'open')->get(['side', 'price', 'quantity']);
```

**If count is 0:** Run seeder
```bash
php artisan db:seed --class=TradingSeeder
```

---

#### **Check 2: Test API Endpoint Directly**

Open browser console and run:

```javascript
// Test orderbook API
fetch('/api/orderbook/BTC%2FUSDT')
    .then(r => r.json())
    .then(data => console.log('Orderbook:', data));

// Should return:
// {
//   bids: [{price: "42000.00000000", quantity: "0.50000000", total: "21000.00000000"}, ...],
//   asks: [{price: "42100.00000000", quantity: "0.50000000", total: "21050.00000000"}, ...],
//   last_price: "0",
//   spread: "100.00000000"
// }
```

**If returns 404:** Check routes are registered
```bash
php artisan route:list --path=api/orderbook
```

**If returns empty arrays:** Database has no open orders, run seeder again

---

#### **Check 3: Verify Frontend is Fetching**

Open browser DevTools (F12) → Console tab

You should see:
```
Orderbook data: {bids: Array(3), asks: Array(3), ...}
```

**If you see error:** Check the error message
- `Failed to fetch` = Backend not running
- `404` = Route not found
- `500` = Server error (check Laravel logs)

---

#### **Check 4: Check Laravel Logs**

```bash
tail -f storage/logs/laravel.log
```

Look for errors related to:
- Database connection
- Query errors
- Service errors

---

## 🧪 Test API Endpoints

### Using Browser Console

```javascript
// 1. Test Orderbook
fetch('/api/orderbook/BTC%2FUSDT')
    .then(r => r.json())
    .then(console.log);

// 2. Test Recent Trades
fetch('/api/trades/BTC%2FUSDT')
    .then(r => r.json())
    .then(console.log);

// 3. Test User Orders (requires auth)
fetch('/api/orders')
    .then(r => r.json())
    .then(console.log);

// 4. Test User Assets (requires auth)
fetch('/api/assets')
    .then(r => r.json())
    .then(console.log);
```

---

## 🔴 Test Real-time Updates

### 1. Place Order Test

1. Open Exchange page
2. Open browser console (F12)
3. Fill order form:
   - Price: `42000`
   - quantity: `0.1`
4. Click "Buy BTC"
5. Watch console for:
   ```
   Orderbook updated: {...}
   ```

### 2. Two Browser Test (Real-time)

1. **Browser A:** Login as trader1@example.com
2. **Browser B:** Login as trader2@example.com
3. **Browser A:** Place buy order @ 42100
4. **Browser B:** Should see orderbook update immediately!

---

## 📊 Expected Data After Seeder

### Orders in Database

**Buy Orders (Bids):**
```
42000.00 | 0.50 BTC
41900.00 | 1.00 BTC
41800.00 | 0.75 BTC
```

**Sell Orders (Asks):**
```
42100.00 | 0.50 BTC
42200.00 | 1.00 BTC
42300.00 | 0.75 BTC
```

### User Balances

**trader1@example.com:**
- USDT: 100,000
- BTC: 2

**trader2@example.com:**
- USDT: 50,000
- BTC: 5

---

## 🐛 Common Issues & Solutions

### Issue: "Insufficient balance"

**Solution:** Check user has enough balance
```bash
php artisan tinker
```
```php
$user = \App\Models\User::where('email', 'trader1@example.com')->first();
$user->assets;
```

---

### Issue: Orders not matching

**Reason:** Our matching engine only matches **exact quantitys**

**Example:**
- Buy order: 0.5 BTC @ 42100
- Sell order: 0.5 BTC @ 42100
- ✅ Will match!

- Buy order: 0.5 BTC @ 42100
- Sell order: 1.0 BTC @ 42100
- ❌ Won't match (different quantitys)

---

### Issue: WebSocket not connecting

**Check Reverb is running:**
```bash
php artisan reverb:start
```

**Check .env has correct config:**
```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
```

**Test in console:**
```javascript
Echo.channel('orderbook.BTC/USDT')
    .listen('.orderbook.updated', (e) => {
        console.log('Connected!', e);
    });
```

---

## 🎯 Quick Tests Checklist

- [ ] Database has orders (`php artisan tinker`)
- [ ] API returns data (`fetch('/api/orderbook/BTC%2FUSDT')`)
- [ ] Frontend loads orderbook (check browser console)
- [ ] Can place order
- [ ] Balance updates after order
- [ ] Can cancel order
- [ ] Reverb is running
- [ ] WebSocket connects (check console for "Connected!")
- [ ] Real-time updates work

---

## 🔧 Reset Everything

If everything is broken, start fresh:

```bash
# 1. Stop all services (Ctrl+C in all terminals)

# 2. Fresh database with seed
php artisan migrate:fresh
php artisan db:seed --class=TradingSeeder

# 3. Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 4. Restart frontend
npm run dev

# 5. Start Reverb
php artisan reverb:start

# 6. Refresh browser (Hard refresh: Ctrl+Shift+R)
```

---

## 📝 Debug Checklist

When orderbook is empty, check in order:

1. ✅ Backend running? (`http://localhost` accessible)
2. ✅ Frontend running? (`npm run dev` no errors)
3. ✅ Database has data? (tinker check)
4. ✅ API returns data? (browser console fetch)
5. ✅ Frontend fetches data? (console shows logs)
6. ✅ No JavaScript errors? (browser console clean)
7. ✅ Correct URL encoding? (`BTC%2FUSDT` not `BTC/USDT`)

---

## 🎓 Advanced Debugging

### Enable Query Logging

Add to `AppServiceProvider::boot()`:

```php
\DB::listen(function($query) {
    \Log::info($query->sql, $query->bindings);
});
```

### Check Actual HTTP Request

```javascript
// Add to useOrderbook.ts fetchOrderbook()
console.log('Fetching from:', `/api/orderbook/${encodedSymbol}`);
console.log('Response status:', response.status);
console.log('Response body:', await response.text());
```

---

## ✅ Success Indicators

You'll know everything works when you see:

1. **Orderbook displays** with 3 bids and 3 asks
2. **Balance shows** real numbers (not "0")
3. **Console logs** show "Orderbook data: {...}"
4. **Can place order** and see it in "Open Orders"
5. **Can cancel order** and balance updates
6. **Real-time works** when opening 2 browsers
