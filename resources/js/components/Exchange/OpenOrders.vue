<script setup lang="ts">
interface Order {
    id: number;
    symbol: string;
    side: 'buy' | 'sell';
    price: string;
    amount: string;
    total: string;
}

defineProps<{
    orders: Order[];
    loading: boolean;
    symbol: string;
}>();

const emit = defineEmits<{
    refresh: [];
    cancel: [orderId: number];
}>();
</script>

<template>
    <div class="bg-gray-850 border-t border-gray-700">
        <div class="border-b border-gray-700 px-4 py-3">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Open Orders</h3>
                <button
                    @click="emit('refresh')"
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
            <div v-if="loading" class="flex h-full items-center justify-center">
                <div class="text-center text-xs text-gray-500">
                    <div class="animate-pulse">Loading orders...</div>
                </div>
            </div>

            <div
                v-else-if="orders.length === 0"
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
                    v-for="order in orders"
                    :key="order.id"
                    class="rounded-lg border border-gray-700 bg-gray-900 p-3 transition-colors hover:border-gray-600"
                >
                    <div class="mb-2 flex items-center justify-between">
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
                            @click="emit('cancel', order.id)"
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
                            <div class="mb-0.5 text-gray-500">Price</div>
                            <div class="font-medium text-white">
                                {{ parseFloat(order.price).toLocaleString() }}
                            </div>
                        </div>
                        <div>
                            <div class="mb-0.5 text-gray-500">Amount</div>
                            <div class="font-medium text-white">
                                {{ order.amount }}
                            </div>
                        </div>
                        <div>
                            <div class="mb-0.5 text-gray-500">Total</div>
                            <div class="font-medium text-white">
                                {{ parseFloat(order.total).toFixed(2) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bg-gray-850 {
    background-color: #1a202c;
}
</style>
