<script setup lang="ts">
interface OrderBookItem {
    price: string;
    quantity: string;
    total: string;
}

interface Orderbook {
    asks: OrderBookItem[];
    bids: OrderBookItem[];
    last_price: string;
    spread: string;
}

defineProps<{
    orderbook: Orderbook;
    loading: boolean;
}>();
</script>

<template>
    <div class="w-80 border-r border-gray-700 bg-gray-800">
        <div class="border-b border-gray-700 px-4 py-2">
            <h3 class="text-sm font-semibold">Order Book</h3>
        </div>

        <div
            v-if="loading"
            class="flex h-[calc(100%-2.5rem)] items-center justify-center"
        >
            <div class="text-center text-gray-500">
                <div class="animate-pulse">Loading orderbook...</div>
            </div>
        </div>

        <!-- No more v-else, so orderbook stays visible during refreshes -->
        <div v-show="!loading" class="h-[calc(100%-2.5rem)] overflow-y-auto">
            <!-- Asks (Sell Orders) -->
            <div class="mb-2">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 bg-gray-800 text-gray-500">
                        <tr>
                            <th class="px-3 py-1 text-left font-normal">
                                Price (USD)
                            </th>
                            <th class="px-3 py-1 text-right font-normal">
                                quantity (BTC)
                            </th>
                            <th class="px-3 py-1 text-right font-normal">
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
                            class="group relative cursor-pointer hover:bg-gray-700/30"
                        >
                            <td class="relative px-3 py-1 text-red-400">
                                <div class="relative z-10">
                                    {{ parseFloat(ask.price).toLocaleString() }}
                                </div>
                            </td>
                            <td
                                class="relative px-3 py-1 text-right text-gray-300"
                            >
                                <div class="relative z-10">
                                    {{ ask.quantity }}
                                </div>
                            </td>
                            <td
                                class="relative px-3 py-1 text-right text-gray-500"
                            >
                                <div class="relative z-10">
                                    {{ parseFloat(ask.total).toFixed(2) }}
                                </div>
                                <!-- Background bar inside last td -->
                                <div
                                    class="absolute inset-y-0 right-0 bg-red-900/10"
                                    :style="{
                                        width:
                                            (parseFloat(ask.quantity) / 2.5) *
                                                100 +
                                            '%',
                                    }"
                                ></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Spread -->
            <div class="bg-gray-750 px-3 py-2 text-center">
                <div class="text-lg font-semibold text-green-400">
                    {{ parseFloat(orderbook.last_price).toLocaleString() }}
                </div>
                <div class="text-xs text-gray-500">
                    Spread: {{ orderbook.spread }} ({{
                        (
                            (parseFloat(orderbook.spread) /
                                parseFloat(orderbook.last_price)) *
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
                            class="group relative cursor-pointer hover:bg-gray-700/30"
                        >
                            <td class="relative px-3 py-1 text-green-400">
                                <div class="relative z-10">
                                    {{ parseFloat(bid.price).toLocaleString() }}
                                </div>
                            </td>
                            <td
                                class="relative px-3 py-1 text-right text-gray-300"
                            >
                                <div class="relative z-10">
                                    {{ bid.quantity }}
                                </div>
                            </td>
                            <td
                                class="relative px-3 py-1 text-right text-gray-500"
                            >
                                <div class="relative z-10">
                                    {{ parseFloat(bid.total).toFixed(2) }}
                                </div>
                                <!-- Background bar inside last td -->
                                <div
                                    class="absolute inset-y-0 right-0 bg-green-900/10"
                                    :style="{
                                        width:
                                            (parseFloat(bid.quantity) / 2.5) *
                                                100 +
                                            '%',
                                    }"
                                ></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-gray-750 {
    background-color: #2d3748;
}
</style>
