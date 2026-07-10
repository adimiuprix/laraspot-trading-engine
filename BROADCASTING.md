# Broadcasting & Real-time Updates with Reverb

This document explains how the real-time broadcasting works in the trading system using Laravel Reverb.

## 📡 Available Channels

### Public Channels (No Authentication Required)

#### 1. **Orderbook Channel**
```
Channel: orderbook.{symbol}
Example: orderbook.BTC/USDT
```

**Events:**
- `orderbook.updated` - Fired when:
  - New order placed
  - Order cancelled
  - Trade executed (orderbook changed)

**Event Data:**
```javascript
{
  type: 'order_placed' | 'order_cancelled',
  order: {
    id: number,
    side: 'buy' | 'sell',
    price: string,
    quantity: string,
    status: string
  }
}
```

---

#### 2. **Trades Channel**
```
Channel: trades.{symbol}
Example: trades.BTC/USDT
```

**Events:**
- `trade.executed` - Fired when trade happens

**Event Data:**
```javascript
{
  trade: {
    price: string,
    quantity: string,
    total: string,
    side: 'buy' | 'sell',
    time: string (ISO8601)
  }
}
```

---

### Private Channels (Authentication Required)

#### 3. **User Channel**
```
Channel: user.{userId}
Example: user.123
```

**Events:**
- `order.status.changed` - When user's order status changes
- `balance.updated` - When user's balance changes

**Event Data (order.status.changed):**
```javascript
{
  order: {
    id: number,
    symbol: string,
    side: 'buy' | 'sell',
    price: string,
    quantity: string,
    status: 'open' | 'filled' | 'cancelled',
    updated_at: string
  }
}
```

**Event Data (balance.updated):**
```javascript
{
  asset: {
    symbol: string,
    quantity: string,
    locked_quantity: string,
    available: string,
    total: string
  }
}
```

---

## 🔧 Frontend Integration (Vue 3)

### Setup (Already Done in app.ts)

```typescript
import { configureEcho } from '@laravel/echo-vue';

configureEcho({
    broadcaster: 'reverb',
});
```

---

### Example: Exchange Page with Real-time Updates

```vue
<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useEcho } from '@laravel/echo-vue';

const echo = useEcho();

// State
const orderbook = ref({ bids: [], asks: [] });
const recentTrades = ref([]);
const userOrders = ref([]);
const balance = ref({ BTC: '0', USDT: '0' });

// Symbol to watch
const symbol = 'BTC/USDT';
const userId = 123; // From auth

onMounted(() => {
    // Subscribe to orderbook updates
    echo
        .channel(`orderbook.${symbol}`)
        .listen('.orderbook.updated', (event) => {
            console.log('Orderbook updated:', event);
            
            // Refresh orderbook from API or update state
            fetchOrderBook();
        });

    // Subscribe to trades
    echo
        .channel(`trades.${symbol}`)
        .listen('.trade.executed', (event) => {
            console.log('New trade:', event.trade);
            
            // Add to recent trades (prepend)
            recentTrades.value.unshift(event.trade);
            
            // Keep only last 50 trades
            if (recentTrades.value.length > 50) {
                recentTrades.value.pop();
            }
        });

    // Subscribe to private user channel
    echo
        .private(`user.${userId}`)
        .listen('.order.status.changed', (event) => {
            console.log('Order status changed:', event.order);
            
            // Update user's order in the list
            const index = userOrders.value.findIndex(o => o.id === event.order.id);
            if (index !== -1) {
                userOrders.value[index] = event.order;
            }
        })
        .listen('.balance.updated', (event) => {
            console.log('Balance updated:', event.asset);
            
            // Update balance display
            balance.value[event.asset.symbol] = event.asset.available;
        });

    // Initial data fetch
    fetchOrderBook();
    fetchRecentTrades();
    fetchUserOrders();
    fetchBalance();
});

onUnmounted(() => {
    // Cleanup subscriptions
    echo.leave(`orderbook.${symbol}`);
    echo.leave(`trades.${symbol}`);
    echo.leave(`user.${userId}`);
});

// API functions
async function fetchOrderBook() {
    const response = await fetch(`/api/orderbook/${symbol}`);
    orderbook.value = await response.json();
}

async function fetchRecentTrades() {
    const response = await fetch(`/api/trades/${symbol}`);
    recentTrades.value = await response.json();
}

async function fetchUserOrders() {
    const response = await fetch('/api/orders');
    userOrders.value = await response.json();
}

async function fetchBalance() {
    const response = await fetch('/api/assets');
    const assets = await response.json();
    assets.forEach(asset => {
        balance.value[asset.symbol] = asset.available;
    });
}
</script>

<template>
    <div>
        <!-- Your trading UI here -->
        <!-- Orderbook, trades, orders will update automatically via WebSocket -->
    </div>
</template>
```

---

## 🚀 Testing Broadcasting

### 1. Start Reverb Server
```bash
php artisan reverb:start
```

### 2. Start Queue Worker (if using queued broadcasts)
```bash
php artisan queue:work
```

### 3. Test in Browser Console

**Subscribe to public channel:**
```javascript
Echo.channel('orderbook.BTC/USDT')
    .listen('.orderbook.updated', (e) => {
        console.log('Orderbook updated:', e);
    });
```

**Subscribe to private channel:**
```javascript
Echo.private('user.123')
    .listen('.order.status.changed', (e) => {
        console.log('Order changed:', e);
    })
    .listen('.balance.updated', (e) => {
        console.log('Balance updated:', e);
    });
```

---

## 🔐 Authentication for Private Channels

Private channels automatically use Laravel Sanctum authentication. The authorization is defined in `routes/channels.php`:

```php
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

This ensures users can only subscribe to their own private channel.

---

## 📊 Event Flow Example

### Scenario: User Places Buy Order

```
1. User submits order via API
   POST /api/orders

2. OrderService creates order
   └─> event(OrderPlaced)
       └─> Broadcast to: orderbook.BTC/USDT
       └─> Frontend receives, refreshes orderbook

3. OrderService locks balance
   └─> event(BalanceUpdated)
       └─> Broadcast to: user.{userId}
       └─> Frontend receives, updates balance display

4. OrderMatchingEngine tries match
   └─> If matched:
       └─> event(TradeExecuted)
           ├─> Broadcast to: trades.BTC/USDT
           ├─> Broadcast to: orderbook.BTC/USDT
           └─> Frontend receives, updates trades & orderbook

       └─> event(OrderStatusChanged) x2 (buyer & seller)
           └─> Broadcast to: user.{buyerId}
           └─> Broadcast to: user.{sellerId}
           └─> Frontend receives, updates user orders

       └─> event(BalanceUpdated) x4 (both users, both assets)
           └─> Broadcast to: user.{buyerId}
           └─> Broadcast to: user.{sellerId}
           └─> Frontend receives, updates balances
```

---

## 🎯 Benefits

✅ **Real-time Updates** - No polling, instant updates  
✅ **Scalable** - Reverb handles thousands of connections  
✅ **Type-safe** - Event names are consistent  
✅ **Automatic** - Events triggered in Services  
✅ **Secure** - Private channels require authentication  

---

## 🔧 Configuration

Broadcasting configuration is in `config/broadcasting.php` and `.env`:

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

Frontend env variables:

```env
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```
