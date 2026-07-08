<script setup lang="ts">
import { computed } from 'vue';

interface OrderForm {
    price: string;
    amount: string;
    total: string;
}

const props = defineProps<{
    orderForm: OrderForm;
    orderType: 'limit' | 'market';
    activeOrderTab: 'buy' | 'sell';
    currentPrice: string;
    currentBalance: string;
    estimatedFee: string;
    totalWithFee: string;
    submitting: boolean;
    submitError: string | null;
    submitSuccess: boolean;
    assetsLoading: boolean;
}>();

const emit = defineEmits<{
    'update-price': [value: string];
    'update-amount': [value: string];
    'set-order-tab': [tab: 'buy' | 'sell'];
    'set-order-type': [type: 'limit' | 'market'];
    'validate-input': [event: Event];
    'calculate-total': [];
    'set-percentage': [percent: number];
    submit: [];
}>();

const assetSymbol = computed(() =>
    props.activeOrderTab === 'buy' ? 'USDT' : 'BTC',
);
</script>

<template>
    <div class="flex w-96 flex-col bg-gray-800">
        <div class="flex-1 overflow-y-auto">
            <div class="p-4">
                <!-- Buy/Sell Tabs -->
                <div class="mb-4 flex rounded-lg bg-gray-900 p-1">
                    <button
                        @click="emit('set-order-tab', 'buy')"
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
                        @click="emit('set-order-tab', 'sell')"
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
                        @click="emit('set-order-type', 'limit')"
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
                        @click="emit('set-order-type', 'market')"
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
                        <span v-else class="text-lg font-semibold text-white">
                            {{ parseFloat(currentBalance).toLocaleString() }}
                        </span>
                        <span class="text-sm text-gray-400">
                            {{ assetSymbol }}
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
                <form @submit.prevent="emit('submit')" class="space-y-4">
                    <!-- Price Input (Hidden for Market Orders) -->
                    <div v-if="orderType === 'limit'">
                        <label
                            class="mb-2 block text-xs font-medium text-gray-300"
                            >Price</label
                        >
                        <div class="relative">
                            <input
                                :value="orderForm.price"
                                @input="
                                    emit(
                                        'update-price',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    );
                                    emit('validate-input', $event);
                                "
                                @blur="emit('calculate-total')"
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
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-400"
                                    >Market Price</span
                                >
                                <span class="text-sm font-semibold text-white">
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
                                :value="orderForm.amount"
                                @input="
                                    emit(
                                        'update-amount',
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    );
                                    emit('validate-input', $event);
                                "
                                @blur="emit('calculate-total')"
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
                            @click="emit('set-percentage', percent)"
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
                            <span class="text-gray-400">Fee (0.1%)</span>
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
    </div>
</template>
