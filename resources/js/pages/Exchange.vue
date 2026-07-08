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

// State untuk order type (Limit/Market)
const orderType = ref('limit');

// State untuk order form
const orderForm = ref({
    price: '',
    amount: '',
    total: '',
});

// Loading state for order submission
const submitting = ref(false);
const submitError = ref<string | null>(null);
const submitSuccess = ref(false);

// Computed values
const currentBalance = computed(() => {
    const asset = activeOrderTab.value === 'buy' ? 'USDT' : 'BTC';
    const balance = getAvailableBalance(asset);
    console.log('Current Balance:', {
        asset,
        balance,
        allAssets: assets.value,
    });
    return balance;
});

const currentPrice = computed(() => {
    return orderbook.value.last_price || '0';
});

const estimatedFee = computed(() => {
    if (!orderForm.value.total) return '0.00';
    const total = parseFloat(orderForm.value.total);
    return (total * 0.001).toFixed(8); // 0.1% fee
});

const totalWithFee = computed(() => {
    if (!orderForm.value.total) return '0.00';
    const total = parseFloat(orderForm.value.total);
    const fee = parseFloat(estimatedFee.value);
    return (total + fee).toFixed(8);
});

// Functions
function logout() {
    router.post('/logout');
}

function setOrderTab(tab: 'buy' | 'sell') {
    activeOrderTab.value = tab;
    orderForm.value = { price: '', amount: '', total: '' };
    submitError.value = null;
    submitSuccess.value = false;
}

function setOrderType(type: 'limit' | 'market') {
    orderType.value = type;
    if (type === 'market') {
        // Market orders use current price
        orderForm.value.price = currentPrice.value;
    } else {
        orderForm.value.price = '';
    }
    calculateTotal();
}

function calculateTotal() {
    const price = orderForm.value.price ? parseFloat(orderForm.value.price) : 0;
    const amount = orderForm.value.amount
        ? parseFloat(orderForm.value.amount)
        : 0;

    if (price && amount) {
        const total = price * amount;
        orderForm.value.total = total.toFixed(8);
    } else {
        orderForm.value.total = '';
    }
}

function validateNumberInput(event: Event) {
    const input = event.target as HTMLInputElement;
    const value = input.value;

    // Allow only numbers, one decimal point, and backspace
    const regex = /^[0-9]*\.?[0-9]*$/;

    if (!regex.test(value)) {
        // Remove invalid characters
        input.value = value.replace(/[^0-9.]/g, '');

        // Ensure only one decimal point
        const parts = input.value.split('.');
        if (parts.length > 2) {
            input.value = parts[0] + '.' + parts.slice(1).join('');
        }

        // Update v-model manually
        if (input === document.activeElement) {
            const field = input.getAttribute('data-field');
            if (field === 'price') {
                orderForm.value.price = input.value;
            } else if (field === 'amount') {
                orderForm.value.amount = input.value;
            }
        }
    }
}

function setPercentage(percent: number) {
    const balance = parseFloat(currentBalance.value);

    if (!orderForm.value.price) {
        submitError.value = 'Please enter price first';
        setTimeout(() => {
            submitError.value = null;
        }, 3000);
        return;
    }

    if (!balance || balance === 0) {
        submitError.value = 'Insufficient balance';
        setTimeout(() => {
            submitError.value = null;
        }, 3000);
        return;
    }

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
    if (orderType.value === 'limit' && !orderForm.value.price) {
        submitError.value = 'Price is required for limit orders';
        return;
    }

    if (!orderForm.value.amount) {
        submitError.value = 'Amount is required';
        return;
    }

    try {
        submitting.value = true;
        submitError.value = null;
        submitSuccess.value = false;

        await submitOrder({
            symbol,
            side: activeOrderTab.value,
            price:
                orderType.value === 'market'
                    ? currentPrice.value
                    : orderForm.value.price,
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
        submitError.value =
            error instanceof Error ? error.message : 'Failed to place order';
    } finally {
        submitting.value = false;
    }
}

async function cancelOrder(orderId: number) {
    if (!confirm('Are you sure you want to cancel this order?')) return;

    try {
        await removeOrder(orderId);
    } catch (error) {
        alert(
            'Failed to cancel order: ' +
                (error instanceof Error ? error.message : 'Unknown error'),
        );
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
        <nav class="border-b border-gray-700 bg-gray-800">
            <div class="px-4">
                <div class="flex h-14 justify-between">
                    <div class="flex items-center space-x-8">
                        <h1 class="text-xl font-bold text-indigo-400">
                            Scavence
                        </h1>
                        <div class="flex items-center space-x-1">
                            <div class="text-sm">
                                <div class="flex items-baseline space-x-2">
                                    <span class="text-2xl font-semibold"
                                        >42,150.50</span
                                    >
                                    <span class="text-xs text-gray-400"
                                        >USD</span
                                    >
                                </div>
                                <div class="text-xs text-green-400">
                                    +2.5% (24h)
                                </div>
                            </div>
                        </div>
                        <div
                            class="flex items-center space-x-4 text-xs text-gray-400"
                        >
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
                            class="rounded bg-gray-700 px-4 py-1.5 text-sm transition hover:bg-gray-600"
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
            <div class="flex flex-1 flex-col border-r border-gray-700">
                <!-- Chart Area -->
                <div class="bg-gray-850 flex-1 p-4">
                    <div
                        class="flex h-full items-center justify-center rounded border border-gray-700"
                    >
                        <div class="text-center text-gray-500">
                            <svg
                                class="mx-auto mb-2 h-16 w-16"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"
                                ></path>
                            </svg>
                            <p>Chart Placeholder</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Trades -->
                <div class="h-64 border-t border-gray-700 bg-gray-800">
                    <div class="border-b border-gray-700 px-4 py-2">
                        <h3 class="text-sm font-semibold">Recent Trades</h3>
                    </div>

                    <div
                        v-if="tradesLoading"
                        class="flex h-[calc(100%-2.5rem)] items-center justify-center"
                    >
                        <div class="text-center text-xs text-gray-500">
                            <div class="animate-pulse">Loading trades...</div>
                        </div>
                    </div>

                    <div v-else class="h-[calc(100%-2.5rem)] overflow-y-auto">
                        <table class="w-full text-xs">
                            <thead
                                class="sticky top-0 border-b border-gray-700 bg-gray-800 text-gray-500"
                            >
                                <tr>
                                    <th class="px-4 py-2 text-left font-normal">
                                        Price (USD)
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-normal"
                                    >
                                        Amount (BTC)
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right font-normal"
                                    >
                                        Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(trade, index) in recentTrades"
                                    :key="index"
                                    class="hover:bg-gray-750 border-b border-gray-700/50"
                                >
                                    <td
                                        class="px-4 py-2"
                                        :class="
                                            trade.side === 'buy'
                                                ? 'text-green-400'
                                                : 'text-red-400'
                                        "
                                    >
                                        {{
                                            parseFloat(
                                                trade.price,
                                            ).toLocaleString()
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right text-gray-300"
                                    >
                                        {{ trade.amount }}
                                    </td>
                                    <td
                                        class="px-4 py-2 text-right text-gray-500"
                                    >
                                        {{ formatTime(trade.time) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Middle Column: Orderbook -->
            <div class="w-80 border-r border-gray-700 bg-gray-800">
                <div class="border-b border-gray-700 px-4 py-2">
                    <h3 class="text-sm font-semibold">Order Book</h3>
                </div>

                <div
                    v-if="orderbookLoading"
                    class="flex h-[calc(100%-2.5rem)] items-center justify-center"
                >
                    <div class="text-center text-gray-500">
                        <div class="animate-pulse">Loading orderbook...</div>
                    </div>
                </div>

                <div v-else class="h-[calc(100%-2.5rem)] overflow-y-auto">
                    <!-- Asks (Sell Orders) -->
                    <div class="mb-2">
                        <table class="w-full text-xs">
                            <thead
                                class="sticky top-0 bg-gray-800 text-gray-500"
                            >
                                <tr>
                                    <th class="px-3 py-1 text-left font-normal">
                                        Price (USD)
                                    </th>
                                    <th
                                        class="px-3 py-1 text-right font-normal"
                                    >
                                        Amount (BTC)
                                    </th>
                                    <th
                                        class="px-3 py-1 text-right font-normal"
                                    >
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(ask, index) in orderbook.asks
                                        .slice()
                                        .reverse()"
                                    :key="'ask-' + index"
                                    class="hover:bg-gray-750 relative cursor-pointer"
                                >
                                    <td
                                        class="relative z-10 px-3 py-1 text-red-400"
                                    >
                                        {{
                                            parseFloat(
                                                ask.price,
                                            ).toLocaleString()
                                        }}
                                    </td>
                                    <td
                                        class="relative z-10 px-3 py-1 text-right text-gray-300"
                                    >
                                        {{ ask.amount }}
                                    </td>
                                    <td
                                        class="relative z-10 px-3 py-1 text-right text-gray-500"
                                    >
                                        {{ parseFloat(ask.total).toFixed(2) }}
                                    </td>
                                    <div
                                        class="absolute inset-0 bg-red-900/10"
                                        :style="{
                                            width:
                                                (parseFloat(ask.amount) / 2.5) *
                                                    100 +
                                                '%',
                                        }"
                                    ></div>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Spread -->
                    <div class="bg-gray-750 px-3 py-2 text-center">
                        <div class="text-lg font-semibold text-green-400">
                            {{ parseFloat(currentPrice).toLocaleString() }}
                        </div>
                        <div class="text-xs text-gray-500">
                            Spread: {{ orderbook.spread }} ({{
                                (
                                    (parseFloat(orderbook.spread) /
                                        parseFloat(currentPrice)) *
                                    100
                                ).toFixed(2)
                            }}%)
                        </div>
                    </div>

                    <!-- Bids (Buy Orders) -->
                    <div class="mt-2">
                        <table class="w-full text-xs">
                            <tbody>
                                <tr
                                    v-for="(bid, index) in orderbook.bids"
                                    :key="'bid-' + index"
                                    class="hover:bg-gray-750 relative cursor-pointer"
                                >
                                    <td
                                        class="relative z-10 px-3 py-1 text-green-400"
                                    >
                                        {{
                                            parseFloat(
                                                bid.price,
                                            ).toLocaleString()
                                        }}
                                    </td>
                                    <td
                                        class="relative z-10 px-3 py-1 text-right text-gray-300"
                                    >
                                        {{ bid.amount }}
                                    </td>
                                    <td
                                        class="relative z-10 px-3 py-1 text-right text-gray-500"
                                    >
                                        {{ parseFloat(bid.total).toFixed(2) }}
                                    </td>
                                    <div
                                        class="absolute inset-0 bg-green-900/10"
                                        :style="{
                                            width:
                                                (parseFloat(bid.amount) / 2.5) *
                                                    100 +
                                                '%',
                                        }"
                                    ></div>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column: Trading Panel -->
            <div class="flex w-96 flex-col bg-gray-800">
                <div class="flex-1 overflow-y-auto">
                    <div class="p-4">
                        <!-- Buy/Sell Tabs -->
                        <div class="mb-4 flex rounded-lg bg-gray-900 p-1">
                            <button
                                @click="setOrderTab('buy')"
                                class="flex-1 rounded-md py-2.5 text-sm font-semibold transition-all"
                                :class="
                                    activeOrderTab === 'buy'
                                        ? 'bg-green-600 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white'
                                "
                            >
                                Buy
                            </button>
                            <button
                                @click="setOrderTab('sell')"
                                class="flex-1 rounded-md py-2.5 text-sm font-semibold transition-all"
                                :class="
                                    activeOrderTab === 'sell'
                                        ? 'bg-red-600 text-white shadow-lg'
                                        : 'text-gray-400 hover:text-white'
                                "
                            >
                                Sell
                            </button>
                        </div>

                        <!-- Order Type Tabs -->
                        <div class="mb-6 flex border-b border-gray-700">
                            <button
                                @click="setOrderType('limit')"
                                class="relative px-4 py-2 text-sm font-medium transition-all"
                                :class="
                                    orderType === 'limit'
                                        ? 'text-white'
                                        : 'text-gray-500 hover:text-gray-300'
                                "
                            >
                                Limit
                                <div
                                    v-if="orderType === 'limit'"
                                    class="absolute right-0 bottom-0 left-0 h-0.5 bg-indigo-500"
                                ></div>
                            </button>
                            <button
                                @click="setOrderType('market')"
                                class="relative px-4 py-2 text-sm font-medium transition-all"
                                :class="
                                    orderType === 'market'
                                        ? 'text-white'
                                        : 'text-gray-500 hover:text-gray-300'
                                "
                            >
                                Market
                                <div
                                    v-if="orderType === 'market'"
                                    class="absolute right-0 bottom-0 left-0 h-0.5 bg-indigo-500"
                                ></div>
                            </button>
                        </div>

                        <!-- Balance Display -->
                        <div class="mb-6 rounded-lg bg-gray-900 p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs text-gray-400"
                                    >Available Balance</span
                                >
                                <button
                                    class="text-xs text-indigo-400 hover:text-indigo-300"
                                >
                                    <svg
                                        class="mr-1 inline h-3 w-3"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                        ></path>
                                    </svg>
                                    Refresh
                                </button>
                            </div>
                            <div class="flex items-baseline gap-2">
                                <span
                                    v-if="assetsLoading"
                                    class="animate-pulse text-lg font-semibold text-gray-300"
                                >
                                    Loading...
                                </span>
                                <span
                                    v-else
                                    class="text-lg font-semibold text-white"
                                >
                                    {{
                                        parseFloat(
                                            currentBalance,
                                        ).toLocaleString()
                                    }}
                                </span>
                                <span class="text-sm text-gray-400">
                                    {{
                                        activeOrderTab === 'buy'
                                            ? 'USDT'
                                            : 'BTC'
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Success Message -->
                        <div
                            v-if="submitSuccess"
                            class="mb-4 flex items-start gap-3 rounded-lg border border-green-500/50 bg-green-900/20 p-3"
                        >
                            <svg
                                class="mt-0.5 h-5 w-5 flex-shrink-0 text-green-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                ></path>
                            </svg>
                            <div class="text-sm text-green-400">
                                <div class="font-medium">
                                    Order placed successfully!
                                </div>
                                <div class="mt-0.5 text-xs text-green-400/70">
                                    Your order has been added to the order book
                                </div>
                            </div>
                        </div>

                        <!-- Error Message -->
                        <div
                            v-if="submitError"
                            class="mb-4 flex items-start gap-3 rounded-lg border border-red-500/50 bg-red-900/20 p-3"
                        >
                            <svg
                                class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                ></path>
                            </svg>
                            <div class="text-sm text-red-400">
                                {{ submitError }}
                            </div>
                        </div>

                        <!-- Order Form -->
                        <form @submit.prevent="placeOrder" class="space-y-4">
                            <!-- Price Input (Hidden for Market Orders) -->
                            <div v-if="orderType === 'limit'">
                                <label
                                    class="mb-2 block text-xs font-medium text-gray-300"
                                    >Price</label
                                >
                                <div class="relative">
                                    <input
                                        v-model="orderForm.price"
                                        @input="validateNumberInput"
                                        @blur="calculateTotal"
                                        type="text"
                                        inputmode="decimal"
                                        data-field="price"
                                        placeholder="0.00"
                                        class="w-full rounded-lg border border-gray-700 bg-gray-900 px-4 py-3 text-sm text-white placeholder-gray-500 focus:border-transparent focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    />
                                    <span
                                        class="absolute top-3 right-4 text-xs font-medium text-gray-500"
                                        >USDT</span
                                    >
                                </div>
                            </div>

                            <!-- Market Price Display (for Market Orders) -->
                            <div v-else>
                                <label
                                    class="mb-2 block text-xs font-medium text-gray-300"
                                    >Price</label
                                >
                                <div
                                    class="rounded-lg border border-gray-700 bg-gray-900 p-3"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span class="text-sm text-gray-400"
                                            >Market Price</span
                                        >
                                        <span
                                            class="text-sm font-semibold text-white"
                                        >
                                            {{
                                                parseFloat(
                                                    currentPrice,
                                                ).toLocaleString()
                                            }}
                                            USDT
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Amount Input -->
                            <div>
                                <label
                                    class="mb-2 block text-xs font-medium text-gray-300"
                                    >Amount</label
                                >
                                <div class="relative">
                                    <input
                                        v-model="orderForm.amount"
                                        @input="validateNumberInput"
                                        @blur="calculateTotal"
                                        type="text"
                                        inputmode="decimal"
                                        data-field="amount"
                                        placeholder="0.00"
                                        class="w-full rounded-lg border border-gray-700 bg-gray-900 px-4 py-3 text-sm text-white placeholder-gray-500 focus:border-transparent focus:ring-2 focus:ring-indigo-500 focus:outline-none"
                                    />
                                    <span
                                        class="absolute top-3 right-4 text-xs font-medium text-gray-500"
                                        >BTC</span
                                    >
                                </div>
                            </div>

                            <!-- Percentage Buttons -->
                            <div class="grid grid-cols-4 gap-2">
                                <button
                                    v-for="percent in [25, 50, 75, 100]"
                                    :key="percent"
                                    type="button"
                                    @click="setPercentage(percent)"
                                    class="rounded-lg border border-gray-700 bg-gray-900 py-2 text-xs font-medium transition-all hover:border-gray-600 hover:bg-gray-700"
                                >
                                    {{ percent }}%
                                </button>
                            </div>

                            <!-- Total & Fee Display -->
                            <div class="space-y-2 rounded-lg bg-gray-900 p-3">
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-400">Total</span>
                                    <span class="font-medium text-white">
                                        {{ orderForm.total || '0.00' }} USDT
                                    </span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-400"
                                        >Fee (0.1%)</span
                                    >
                                    <span class="text-gray-300">
                                        {{ estimatedFee }} USDT
                                    </span>
                                </div>
                                <div class="border-t border-gray-800 pt-2">
                                    <div class="flex justify-between text-xs">
                                        <span class="font-medium text-gray-300"
                                            >Total + Fee</span
                                        >
                                        <span class="font-semibold text-white">
                                            {{ totalWithFee }} USDT
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                :disabled="submitting || assetsLoading"
                                class="w-full rounded-lg py-3.5 text-sm font-semibold shadow-lg transition-all disabled:cursor-not-allowed disabled:opacity-50"
                                :class="
                                    activeOrderTab === 'buy'
                                        ? 'bg-green-600 text-white hover:bg-green-700'
                                        : 'bg-red-600 text-white hover:bg-red-700'
                                "
                            >
                                <span
                                    v-if="submitting"
                                    class="flex items-center justify-center gap-2"
                                >
                                    <svg
                                        class="h-4 w-4 animate-spin"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                            fill="none"
                                        ></circle>
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        ></path>
                                    </svg>
                                    Processing...
                                </span>
                                <span v-else>
                                    {{
                                        activeOrderTab === 'buy'
                                            ? 'Buy BTC'
                                            : 'Sell BTC'
                                    }}
                                </span>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Open Orders Section (Fixed at Bottom) -->
                <div class="bg-gray-850 border-t border-gray-700">
                    <div class="border-b border-gray-700 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold">Open Orders</h3>
                            <button
                                @click="fetchOrders('open')"
                                class="text-xs text-indigo-400 transition-colors hover:text-indigo-300"
                            >
                                <svg
                                    class="mr-1 inline h-3 w-3"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                    ></path>
                                </svg>
                                Refresh
                            </button>
                        </div>
                    </div>

                    <div class="h-64 overflow-y-auto">
                        <div
                            v-if="ordersLoading"
                            class="flex h-full items-center justify-center"
                        >
                            <div class="text-center text-xs text-gray-500">
                                <div class="animate-pulse">
                                    Loading orders...
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="userOrders.length === 0"
                            class="flex h-full flex-col items-center justify-center text-gray-500"
                        >
                            <svg
                                class="mb-2 h-12 w-12 opacity-50"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                ></path>
                            </svg>
                            <p class="text-xs">No open orders</p>
                        </div>

                        <div v-else class="space-y-2 p-3">
                            <div
                                v-for="order in userOrders"
                                :key="order.id"
                                class="rounded-lg border border-gray-700 bg-gray-900 p-3 transition-colors hover:border-gray-600"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="rounded px-2 py-0.5 text-xs font-semibold"
                                            :class="
                                                order.side === 'buy'
                                                    ? 'border border-green-500/30 bg-green-900/30 text-green-400'
                                                    : 'border border-red-500/30 bg-red-900/30 text-red-400'
                                            "
                                        >
                                            {{ order.side.toUpperCase() }}
                                        </span>
                                        <span class="text-xs text-gray-400">{{
                                            symbol
                                        }}</span>
                                    </div>
                                    <button
                                        @click="cancelOrder(order.id)"
                                        class="text-red-400 transition-colors hover:text-red-300"
                                        title="Cancel order"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"
                                            ></path>
                                        </svg>
                                    </button>
                                </div>
                                <div class="grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <div class="mb-0.5 text-gray-500">
                                            Price
                                        </div>
                                        <div class="font-medium text-white">
                                            {{
                                                parseFloat(
                                                    order.price,
                                                ).toLocaleString()
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-0.5 text-gray-500">
                                            Amount
                                        </div>
                                        <div class="font-medium text-white">
                                            {{ order.amount }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-0.5 text-gray-500">
                                            Total
                                        </div>
                                        <div class="font-medium text-white">
                                            {{
                                                parseFloat(order.total).toFixed(
                                                    2,
                                                )
                                            }}
                                        </div>
                                    </div>
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
