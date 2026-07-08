<script setup lang="ts">
import { ref } from 'vue';

interface Order {
    id: number;
    symbol: string;
    side: 'buy' | 'sell';
    price: string;
    amount: string;
    filled_amount?: string;
    remaining_amount?: string;
    total: string;
    status?: 'open' | 'partially_filled' | 'filled' | 'cancelled';
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

const cancellingOrderId = ref<number | null>(null);

async function handleCancel(orderId: number) {
    cancellingOrderId.value = orderId;
    try {
        emit('cancel', orderId);
    } finally {
        // Reset after a short delay to allow WebSocket to remove the order
        setTimeout(() => {
            cancellingOrderId.value = null;
        }, 500);
    }
}

function getFillPercentage(order: Order): number {
    if (!order.filled_amount || !order.amount) return 0;
    const filled = parseFloat(order.filled_amount);
    const total = parseFloat(order.amount);
    return total > 0 ? (filled / total) * 100 : 0;
}

function getStatusColor(status?: string): string {
    switch (status) {
        case 'filled':
            return 'text-green-400';
        case 'partially_filled':
            return 'text-yellow-400';
        case 'cancelled':
            return 'text-red-400';
        default:
            return 'text-blue-400';
    }
}

function getStatusLabel(status?: string): string {
    switch (status) {
        case 'filled':
            return 'Filled';
        case 'partially_filled':
            return 'Partial';
        case 'cancelled':
            return 'Cancelled';
        default:
            return 'Open';
    }
}
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
                    class="rounded-lg border border-gray-700 bg-gray-900 p-3 transition-all"
                    :class="{
                        'opacity-50 pointer-events-none':
                            cancellingOrderId === order.id,
                    }"
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
                            <span
                                v-if="order.status"
                                class="text-xs font-medium"
                                :class="getStatusColor(order.status)"
                            >
                                {{ getStatusLabel(order.status) }}
                            </span>
                        </div>
                        <button
                            @click="handleCancel(order.id)"
                            :disabled="cancellingOrderId === order.id"
                            class="text-red-400 transition-colors hover:text-red-300 disabled:opacity-50"
                            title="Cancel order"
                        >
                            <svg
                                v-if="cancellingOrderId !== order.id"
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
                            <svg
                                v-else
                                class="h-4 w-4 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                        </button>
                    </div>

                    <!-- Fill Progress Bar (if partially filled) -->
                    <div
                        v-if="
                            order.status === 'partially_filled' &&
                            order.filled_amount
                        "
                        class="mb-2"
                    >
                        <div
                            class="mb-1 flex justify-between text-xs text-gray-400"
                        >
                            <span>Filled: {{ order.filled_amount }}</span>
                            <span>{{ getFillPercentage(order).toFixed(1) }}%</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-gray-700">
                            <div
                                class="h-full rounded-full bg-yellow-500 transition-all"
                                :style="{
                                    width: getFillPercentage(order) + '%',
                                }"
                            ></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <div class="mb-0.5 text-gray-500">Price</div>
                            <div class="font-medium text-white">
                                {{ parseFloat(order.price).toLocaleString() }}
                            </div>
                        </div>
                        <div>
                            <div class="mb-0.5 text-gray-500">
                                {{
                                    order.status === 'partially_filled'
                                        ? 'Remaining'
                                        : 'Amount'
                                }}
                            </div>
                            <div class="font-medium text-white">
                                {{
                                    order.status === 'partially_filled' &&
                                    order.remaining_amount
                                        ? order.remaining_amount
                                        : order.amount
                                }}
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
