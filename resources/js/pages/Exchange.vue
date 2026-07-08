<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useOrderbook } from '@/composables/useOrderbook';
import { useTrades } from '@/composables/useTrades';
import { useOrders } from '@/composables/useOrders';
import { useAssets } from '@/composables/useAssets';

// Get user from page props
const page = usePage();
const user = computed(() => page.props.auth.user);

// Trading pair
const symbol = 'BTC/USDT';

// Composables for real-time data
const {
    orderbook,
    loading: orderbookLoading,
    fetchOrderbook,
    subscribeToUpdates: subscribeOrderbook,
    unsubscribe: unsubscribeOrderbook,
} = useOrderbook(symbol);

const {
    trades: recentTrades,
    loading: tradesLoading,
    fetchTrades,
    subscribeToTrades,
    unsubscribe: unsubscribeTrades,
} = useTrades(symbol);

const {
    orders: userOrders,
    loading: ordersLoading,
    error: ordersError,
    fetchOrders,
    placeOrder: submitOrder,
    cancelOrder: removeOrder,
    subscribeToOrderUpdates,
    unsubscribe: unsubscribeOrders,
} = useOrders(user.value.id);

const {
    assets,
    loading: assetsLoading,
    fetchAssets,
    getAvailableBalance,
    subscribeToBalanceUpdates,
    unsubscribe: unsubscribeAssets,
} = useAssets(user.value.id);

// State untuk tab order (Buy/Sell)
const activeOrderTab = ref('buy');

// State untuk order form
const orderForm = ref({
    price: '',
    amount: '',
    total: ''
});

// Loading state for order submission
const submitting = ref(false);
const submitError = ref<string | null>(null);
const submitSuccess = ref(false);

// Computed values
const currentBalance = computed(() => {
    const asset = activeOrderTab.value === 'buy' ? 'USDT' : 'BTC';
    return getAvailableBalance(asset);
});

const currentPrice = computed(() => {
    return orderbook.value.last_price || '0';
});

// Functions
function logout() {
    router.post('/logout');
}

function setOrderTab(tab: 'buy' | 'sell') {
    activeOrderTab.value = tab;
    orderForm.value = { price: '', amount: '', total: '' };
}

function calculateTotal() {
    if (orderForm.value.price && orderForm.value.amount) {
        const total = parseFloat(orderForm.value.price) * parseFloat(orderForm.value.amount);
        orderForm.value.total = total.toFixed(8);
    }
}

function setPercentage(percent: number) {
    const balance = parseFloat(currentBalance.value);
    if (!balance || !orderForm.value.price) return;

    const price = parseFloat(orderForm.value.price);
    
    if (activeOrderTab.value === 'buy') {
        // Calculate amount based on USDT balance
        const availableUsdt = balance * (percent / 100);
        orderForm.value.amount = (availableUsdt / price).toFixed(8);
    } else {
        // Calculate amount based on BTC balance
        orderForm.value.amount = (balance * (percent / 100)).toFixed(8);
    }
    
    calculateTotal();
}

async function placeOrder() {
    if (!orderForm.value.price || !orderForm.value.amount) {
        submitError.value = 'Price and amount are required';
        return;
    }

    try {
        submitting.value = true;
        submitError.value = null;
        submitSuccess.value = false;

        await submitOrder({
            symbol,
            side: activeOrderTab.value,
            price: orderForm.value.price,
            amount: orderForm.value.amount,
        });

        // Success!
        submitSuccess.value = true;
        orderForm.value = { price: '', amount: '', total: '' };

        // Hide success message after 3 seconds
        setTimeout(() => {
            submitSuccess.value = false;
        }, 3000);
    } catch (error) {
        submitError.value = error instanceof Error ? error.message : 'Failed to place order';
    } finally {
        submitting.value = false;
    }
}

async function cancelOrder(orderId: number) {
    if (!confirm('Are you sure you want to cancel this order?')) return;

    try {
        await removeOrder(orderId);
    } catch (error) {
        alert('Failed to cancel order: ' + (error instanceof Error ? error.message : 'Unknown error'));
    }
}

function formatTime(isoString: string) {
    const date = new Date(isoString);
    return date.toLocaleTimeString('en-US', { hour12: false });
}

// Lifecycle
onMounted(async () => {
    // Fetch initial data
    await Promise.all([
        fetchOrderbook(),
        fetchTrades(),
        fetchOrders('open'),
        fetchAssets(),
    ]);

    // Subscribe to real-time updates
    subscribeOrderbook();
    subscribeToTrades();
    subscribeToOrderUpdates();
    subscribeToBalanceUpdates();
});

onUnmounted(() => {
    // Cleanup subscriptions
    unsubscribeOrderbook();
    unsubscribeTrades();
    unsubscribeOrders();
    unsubscribeAssets();
});
</script>

<template>
    <div class="min-h-screen bg-gray-900 text-gray-100">
        <!-- Navigation -->
        <nav class="bg-gray-800 border-b border-gray-700">
            <div class="px-4">
                <div class="flex justify-between h-14">
                    <div class="flex items-center space-x-8">
                        <h1 class="text-xl font-bold text-indigo-400">Scavence</h1>
                        <div class="flex items-center space-x-1">
                            <div class="text-sm">
                                <div class="flex items-baseline space-x-2">
                                    <span class="text-2xl font-semibold">42,150.50</span>
                                    <span class="text-xs text-gray-400">USD</span>
                                </div>
                                <div class="text-xs text-green-400">+2.5% (24h)</div>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4 text-xs text-gray-400">
                            <div>
                                <div class="text-gray-500">24h High</div>
                                <div class="text-gray-200">42,850.00</div>
                            </div>
                            <div>
                                <div class="text-gray-500">24h Low</div>
                                <div class="text-gray-200">41,200.30</div>
                            </div>
                            <div>
                                <div class="text-gray-500">24h Volume</div>
                                <div class="text-gray-200">15,234 BTC</div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-400">
                            {{ $page.props.auth.user.name }}
                        </span>
                        <button
                            @click="logout"
                            class="text-sm px-4 py-1.5 rounded bg-gray-700 hover:bg-gray-600 transition"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Trading Interface -->
        <div class="flex h-[calc(100vh-3.5rem)]">
            <!-- Left Column: Chart & Recent Trades -->
            <div class="flex-1 flex flex-col border-r border-gray-700">
                <!-- Chart Area -->
                <div class="flex-1 bg-gray-850 p-4">
                    <div class="h-full border border-gray-700 rounded flex items-center justify-center">
                        <div class="text-center text-gray-500">
                            <svg class="w-16 h-16 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                            </svg>
                            <p>Chart Placeholder</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Trades -->
                <div class="h-64 bg-gray-800 border-t border-gray-700">
                    <div class="px-4 py-2 border-b border-gray-700">
                        <h3 class="text-sm font-semibold">Recent Trades</h3>
                    </div>
                    
                    <div v-if="tradesLoading" class="flex items-center justify-center h-[calc(100%-2.5rem)]">
                        <div class="text-center text-gray-500 text-xs">
                            <div class="animate-pulse">Loading trades...</div>
                        </div>
                    </div>

                    <div v-else class="overflow-y-auto h-[calc(100%-2.5rem)]">
                        <table class="w-full text-xs">
                            <thead class="text-gray-500 border-b border-gray-700 sticky top-0 bg-gray-800">
                                <tr>
                                    <th class="px-4 py-2 text-left font-normal">Price (USD)</th>
                                    <th class="px-4 py-2 text-right font-normal">Amount (BTC)</th>
                                    <th class="px-4 py-2 text-right font-normal">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(trade, index) in recentTrades"
                                    :key="index"
                                    class="border-b border-gray-700/50 hover:bg-gray-750"
                                >
                                    <td class="px-4 py-2" :class="trade.side === 'buy' ? 'text-green-400' : 'text-red-400'">
                                        {{ parseFloat(trade.price).toLocaleString() }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-300">{{ trade.amount }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">{{ formatTime(trade.time) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Middle Column: Orderbook -->
            <div class="w-80 bg-gray-800 border-r border-gray-700">
                <div class="px-4 py-2 border-b border-gray-700">
                    <h3 class="text-sm font-semibold">Order Book</h3>
                </div>
                
                <div v-if="orderbookLoading" class="h-[calc(100%-2.5rem)] flex items-center justify-center">
                    <div class="text-center text-gray-500">
                        <div class="animate-pulse">Loading orderbook...</div>
                    </div>
                </div>

                <div v-else class="h-[calc(100%-2.5rem)] overflow-y-auto">
                    <!-- Asks (Sell Orders) -->
                    <div class="mb-2">
                        <table class="w-full text-xs">
                            <thead class="text-gray-500 sticky top-0 bg-gray-800">
                                <tr>
                                    <th class="px-3 py-1 text-left font-normal">Price (USD)</th>
                                    <th class="px-3 py-1 text-right font-normal">Amount (BTC)</th>
                                    <th class="px-3 py-1 text-right font-normal">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(ask, index) in orderbook.asks.slice().reverse()"
                                    :key="'ask-' + index"
                                    class="hover:bg-gray-750 cursor-pointer relative"
                                >
                                    <td class="px-3 py-1 text-red-400 relative z-10">
                                        {{ parseFloat(ask.price).toLocaleString() }}
                                    </td>
                                    <td class="px-3 py-1 text-right text-gray-300 relative z-10">{{ ask.amount }}</td>
                                    <td class="px-3 py-1 text-right text-gray-500 relative z-10">{{ parseFloat(ask.total).toFixed(2) }}</td>
                                    <div class="absolute inset-0 bg-red-900/10" :style="{ width: (parseFloat(ask.amount) / 2.5 * 100) + '%' }"></div>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Spread -->
                    <div class="py-2 px-3 bg-gray-750 text-center">
                        <div class="text-lg font-semibold text-green-400">{{ parseFloat(currentPrice).toLocaleString() }}</div>
                        <div class="text-xs text-gray-500">Spread: {{ orderbook.spread }} ({{ ((parseFloat(orderbook.spread) / parseFloat(currentPrice)) * 100).toFixed(2) }}%)</div>
                    </div>

                    <!-- Bids (Buy Orders) -->
                    <div class="mt-2">
                        <table class="w-full text-xs">
                            <tbody>
                                <tr
                                    v-for="(bid, index) in orderbook.bids"
                                    :key="'bid-' + index"
                                    class="hover:bg-gray-750 cursor-pointer relative"
                                >
                                    <td class="px-3 py-1 text-green-400 relative z-10">
                                        {{ parseFloat(bid.price).toLocaleString() }}
                                    </td>
                                    <td class="px-3 py-1 text-right text-gray-300 relative z-10">{{ bid.amount }}</td>
                                    <td class="px-3 py-1 text-right text-gray-500 relative z-10">{{ parseFloat(bid.total).toFixed(2) }}</td>
                                    <div class="absolute inset-0 bg-green-900/10" :style="{ width: (parseFloat(bid.amount) / 2.5 * 100) + '%' }"></div>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Trading Panel -->
            <div class="w-96 bg-gray-800">
                <div class="p-4">
                    <!-- Buy/Sell Tabs -->
                    <div class="flex mb-4 bg-gray-750 rounded p-1">
                        <button
                            @click="setOrderTab('buy')"
                            class="flex-1 py-2 rounded text-sm font-medium transition"
                            :class="activeOrderTab === 'buy' ? 'bg-green-600 text-white' : 'text-gray-400 hover:text-white'"
                        >
                            Buy
                        </button>
                        <button
                            @click="setOrderTab('sell')"
                            class="flex-1 py-2 rounded text-sm font-medium transition"
                            :class="activeOrderTab === 'sell' ? 'bg-red-600 text-white' : 'text-gray-400 hover:text-white'"
                        >
                            Sell
                        </button>
                    </div>

                    <!-- Balance Display -->
                    <div class="mb-4 p-3 bg-gray-750 rounded text-xs">
                        <div class="flex justify-between mb-1">
                            <span class="text-gray-400">Available</span>
                            <span class="text-gray-200">
                                <span v-if="assetsLoading" class="animate-pulse">Loading...</span>
                                <span v-else>
                                    {{ currentBalance }} {{ activeOrderTab === 'buy' ? 'USDT' : 'BTC' }}
                                </span>
                            </span>
                        </div>
                    </div>

                    <!-- Success Message -->
                    <div v-if="submitSuccess" class="mb-4 p-3 bg-green-900/20 border border-green-500/50 rounded text-sm text-green-400">
                        Order placed successfully!
                    </div>

                    <!-- Error Message -->
                    <div v-if="submitError" class="mb-4 p-3 bg-red-900/20 border border-red-500/50 rounded text-sm text-red-400">
                        {{ submitError }}
                    </div>

                    <!-- Order Form -->
                    <form @submit.prevent="placeOrder" class="space-y-4">
                        <!-- Price Input -->
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Price</label>
                            <div class="relative">
                                <input
                                    v-model="orderForm.price"
                                    @input="calculateTotal"
                                    type="number"
                                    step="0.01"
                                    placeholder="0.00"
                                    class="w-full bg-gray-750 border border-gray-600 rounded px-3 py-2 text-sm focus:outline-none focus:border-indigo-500"
                                />
                                <span class="absolute right-3 top-2 text-xs text-gray-500">USD</span>
                            </div>
                        </div>

                        <!-- Amount Input -->
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Amount</label>
                            <div class="relative">
                                <input
                                    v-model="orderForm.amount"
                                    @input="calculateTotal"
                                    type="number"
                                    step="0.00001"
                                    placeholder="0.00"
                                    class="w-full bg-gray-750 border border-gray-600 rounded px-3 py-2 text-sm focus:outline-none focus:border-indigo-500"
                                />
                                <span class="absolute right-3 top-2 text-xs text-gray-500">BTC</span>
                            </div>
                        </div>

                        <!-- Percentage Buttons -->
                        <div class="flex gap-2">
                            <button
                                v-for="percent in [25, 50, 75, 100]"
                                :key="percent"
                                type="button"
                                @click="setPercentage(percent)"
                                class="flex-1 py-1 bg-gray-750 hover:bg-gray-700 rounded text-xs transition"
                            >
                                {{ percent }}%
                            </button>
                        </div>

                        <!-- Total -->
                        <div>
                            <label class="block text-xs text-gray-400 mb-1">Total</label>
                            <div class="relative">
                                <input
                                    v-model="orderForm.total"
                                    type="number"
                                    step="0.01"
                                    placeholder="0.00"
                                    readonly
                                    class="w-full bg-gray-750 border border-gray-600 rounded px-3 py-2 text-sm focus:outline-none"
                                />
                                <span class="absolute right-3 top-2 text-xs text-gray-500">USD</span>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="w-full py-3 rounded font-medium transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="activeOrderTab === 'buy' 
                                ? 'bg-green-600 hover:bg-green-700 text-white' 
                                : 'bg-red-600 hover:bg-red-700 text-white'"
                        >
                            <span v-if="submitting">
                                <svg class="inline w-4 h-4 mr-2 animate-spin" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                            <span v-else>
                                {{ activeOrderTab === 'buy' ? 'Buy BTC' : 'Sell BTC' }}
                            </span>
                        </button>
                    </form>

                    <!-- Open Orders Section -->
                    <div class="mt-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold">Open Orders</h3>
                            <button @click="fetchOrders('open')" class="text-xs text-indigo-400 hover:text-indigo-300">
                                <svg class="inline w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Refresh
                            </button>
                        </div>

                        <div v-if="ordersLoading" class="text-center py-8 text-gray-500 text-xs">
                            <div class="animate-pulse">Loading orders...</div>
                        </div>

                        <div v-else-if="userOrders.length === 0" class="text-center py-8 text-gray-500 text-xs">
                            No open orders
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="order in userOrders"
                                :key="order.id"
                                class="p-2 bg-gray-750 rounded text-xs"
                            >
                                <div class="flex justify-between items-start mb-1">
                                    <span :class="order.side === 'buy' ? 'text-green-400' : 'text-red-400'" class="font-semibold">
                                        {{ order.side.toUpperCase() }}
                                    </span>
                                    <button
                                        @click="cancelOrder(order.id)"
                                        class="text-red-400 hover:text-red-300"
                                        title="Cancel order"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="text-gray-400 space-y-0.5">
                                    <div>Price: <span class="text-gray-200">{{ parseFloat(order.price).toLocaleString() }}</span></div>
                                    <div>Amount: <span class="text-gray-200">{{ order.amount }}</span></div>
                                    <div>Total: <span class="text-gray-200">{{ order.total }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: #1f2937;
}

::-webkit-scrollbar-thumb {
    background: #4b5563;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}

.bg-gray-750 {
    background-color: #2d3748;
}

.bg-gray-850 {
    background-color: #1a202c;
}
</style>
