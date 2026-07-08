<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

// State untuk tab order (Buy/Sell)
const activeOrderTab = ref('buy');

// State untuk order form
const orderForm = ref({
    price: '',
    amount: '',
    total: ''
});

// Static orderbook data
const orderbook = ref({
    bids: [
        { price: 42150.50, amount: 1.254, total: 52860.73 },
        { price: 42149.20, amount: 0.850, total: 35826.82 },
        { price: 42148.00, amount: 2.100, total: 88510.80 },
        { price: 42147.30, amount: 0.450, total: 18966.29 },
        { price: 42145.10, amount: 1.750, total: 73753.93 },
        { price: 42144.80, amount: 0.920, total: 38773.22 },
        { price: 42143.50, amount: 1.320, total: 55629.42 },
        { price: 42142.00, amount: 0.680, total: 28656.56 }
    ],
    asks: [
        { price: 42151.00, amount: 0.950, total: 40043.45 },
        { price: 42152.30, amount: 1.420, total: 59856.27 },
        { price: 42153.80, amount: 0.730, total: 30772.27 },
        { price: 42155.00, amount: 2.100, total: 88525.50 },
        { price: 42156.50, amount: 0.850, total: 35832.03 },
        { price: 42157.20, amount: 1.560, total: 65765.23 },
        { price: 42158.40, amount: 0.420, total: 17706.53 },
        { price: 42159.00, amount: 1.890, total: 79680.51 }
    ]
});

// Static recent trades
const recentTrades = ref([
    { price: 42150.80, amount: 0.124, time: '14:23:45', type: 'buy' },
    { price: 42150.20, amount: 0.580, time: '14:23:42', type: 'sell' },
    { price: 42151.00, amount: 0.250, time: '14:23:38', type: 'buy' },
    { price: 42149.50, amount: 1.120, time: '14:23:35', type: 'sell' },
    { price: 42150.00, amount: 0.420, time: '14:23:30', type: 'buy' },
    { price: 42148.80, amount: 0.890, time: '14:23:25', type: 'sell' },
    { price: 42149.20, amount: 0.350, time: '14:23:20', type: 'buy' },
    { price: 42150.50, amount: 0.670, time: '14:23:15', type: 'sell' }
]);

// Functions
function logout() {
    router.post('/logout');
}

function setOrderTab(tab) {
    activeOrderTab.value = tab;
}

function calculateTotal() {
    if (orderForm.value.price && orderForm.value.amount) {
        orderForm.value.total = (parseFloat(orderForm.value.price) * parseFloat(orderForm.value.amount)).toFixed(2);
    }
}

function setPercentage(percent) {
    // Simulasi set percentage dari balance
    orderForm.value.amount = '';
    calculateTotal();
}

function placeOrder() {
    console.log('Order placed:', {
        type: activeOrderTab.value,
        ...orderForm.value
    });
    // Reset form
    orderForm.value = { price: '', amount: '', total: '' };
}
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
                    <div class="overflow-y-auto h-[calc(100%-2.5rem)]">
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
                                    <td class="px-4 py-2" :class="trade.type === 'buy' ? 'text-green-400' : 'text-red-400'">
                                        {{ trade.price.toLocaleString() }}
                                    </td>
                                    <td class="px-4 py-2 text-right text-gray-300">{{ trade.amount }}</td>
                                    <td class="px-4 py-2 text-right text-gray-500">{{ trade.time }}</td>
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
                <div class="h-[calc(100%-2.5rem)] overflow-y-auto">
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
                                        {{ ask.price.toLocaleString() }}
                                    </td>
                                    <td class="px-3 py-1 text-right text-gray-300 relative z-10">{{ ask.amount }}</td>
                                    <td class="px-3 py-1 text-right text-gray-500 relative z-10">{{ ask.total.toFixed(2) }}</td>
                                    <div class="absolute inset-0 bg-red-900/10" :style="{ width: (ask.amount / 2.5 * 100) + '%' }"></div>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Spread -->
                    <div class="py-2 px-3 bg-gray-750 text-center">
                        <div class="text-lg font-semibold text-green-400">42,150.50</div>
                        <div class="text-xs text-gray-500">Spread: 0.50 (0.01%)</div>
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
                                        {{ bid.price.toLocaleString() }}
                                    </td>
                                    <td class="px-3 py-1 text-right text-gray-300 relative z-10">{{ bid.amount }}</td>
                                    <td class="px-3 py-1 text-right text-gray-500 relative z-10">{{ bid.total.toFixed(2) }}</td>
                                    <div class="absolute inset-0 bg-green-900/10" :style="{ width: (bid.amount / 2.5 * 100) + '%' }"></div>
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
                                {{ activeOrderTab === 'buy' ? '10,000.00 USD' : '0.5000 BTC' }}
                            </span>
                        </div>
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
                            class="w-full py-3 rounded font-medium transition"
                            :class="activeOrderTab === 'buy' 
                                ? 'bg-green-600 hover:bg-green-700 text-white' 
                                : 'bg-red-600 hover:bg-red-700 text-white'"
                        >
                            {{ activeOrderTab === 'buy' ? 'Buy BTC' : 'Sell BTC' }}
                        </button>
                    </form>

                    <!-- Open Orders Section -->
                    <div class="mt-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold">Open Orders</h3>
                            <button class="text-xs text-indigo-400 hover:text-indigo-300">View All</button>
                        </div>
                        <div class="text-center py-8 text-gray-500 text-xs">
                            No open orders
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
