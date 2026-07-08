<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import ExchangeNavigation from '@/components/Exchange/ExchangeNavigation.vue';
import OpenOrders from '@/components/Exchange/OpenOrders.vue';
import OrderBook from '@/components/Exchange/OrderBook.vue';
import RecentTrades from '@/components/Exchange/RecentTrades.vue';
import TradingChart from '@/components/Exchange/TradingChart.vue';
import TradingForm from '@/components/Exchange/TradingForm.vue';
import { useAssets } from '@/composables/useAssets';
import { useOrderbook } from '@/composables/useOrderbook';
import { useOrders } from '@/composables/useOrders';
import { useTrades } from '@/composables/useTrades';
import { useTradingForm } from '@/composables/useTradingForm';

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

// Trading form state
const currentPrice = computed(() => orderbook.value.last_price || '0');

const {
    orderForm,
    orderType,
    activeOrderTab,
    estimatedFee,
    totalWithFee,
    setOrderTab,
    setOrderType,
    calculateTotal,
    validateNumberInput,
    setPercentage,
    resetForm,
} = useTradingForm(() => currentPrice.value);

// Order submission state
const submitting = ref(false);
const submitError = ref<string | null>(null);
const submitSuccess = ref(false);

// Computed balance
const currentBalance = computed(() => {
    const asset = activeOrderTab.value === 'buy' ? 'USDT' : 'BTC';

    return getAvailableBalance(asset);
});

// Functions
function handleSetOrderTab(tab: 'buy' | 'sell') {
    setOrderTab(tab);
    submitError.value = null;
    submitSuccess.value = false;
}

function handleSetPercentage(percent: number) {
    try {
        const priceToUse =
            orderType.value === 'market' ? currentPrice.value : undefined;
        setPercentage(percent, currentBalance.value, priceToUse);
    } catch (error) {
        submitError.value =
            error instanceof Error ? error.message : 'An error occurred';
        setTimeout(() => {
            submitError.value = null;
        }, 3000);
    }
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

        submitSuccess.value = true;
        resetForm();

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
    if (!confirm('Are you sure you want to cancel this order?')) {
return;
}

    try {
        await removeOrder(orderId);
    } catch (error) {
        alert(
            'Failed to cancel order: ' +
                (error instanceof Error ? error.message : 'Unknown error'),
        );
    }
}

// Lifecycle
onMounted(async () => {
    await Promise.all([
        fetchOrderbook(),
        fetchTrades(),
        fetchOrders('open'),
        fetchAssets(),
    ]);

    subscribeOrderbook();
    subscribeToTrades();
    subscribeToOrderUpdates();
    subscribeToBalanceUpdates();
});

onUnmounted(() => {
    unsubscribeOrderbook();
    unsubscribeTrades();
    unsubscribeOrders();
    unsubscribeAssets();
});
</script>

<template>
    <div class="min-h-screen bg-gray-900 text-gray-100">
        <!-- Navigation -->
        <ExchangeNavigation :user-name="user.name" />

        <!-- Main Trading Interface -->
        <div class="flex h-[calc(100vh-3.5rem)]">
            <!-- Left Column: Chart & Recent Trades -->
            <div class="flex flex-1 flex-col border-r border-gray-700">
                <TradingChart />
                <RecentTrades :trades="recentTrades" :loading="tradesLoading" />
            </div>

            <!-- Middle Column: Orderbook -->
            <OrderBook :orderbook="orderbook" :loading="orderbookLoading" />

            <!-- Right Column: Trading Panel -->
            <div class="flex flex-col">
                <TradingForm
                    :order-form="orderForm"
                    :order-type="orderType"
                    :active-order-tab="activeOrderTab"
                    :current-price="currentPrice"
                    :current-balance="currentBalance"
                    :estimated-fee="estimatedFee"
                    :total-with-fee="totalWithFee"
                    :submitting="submitting"
                    :submit-error="submitError"
                    :submit-success="submitSuccess"
                    :assets-loading="assetsLoading"
                    @set-order-tab="handleSetOrderTab"
                    @set-order-type="setOrderType"
                    @validate-input="validateNumberInput"
                    @calculate-total="calculateTotal"
                    @set-percentage="handleSetPercentage"
                    @submit="placeOrder"
                />

                <!-- Open Orders Section -->
                <OpenOrders
                    :orders="userOrders"
                    :loading="ordersLoading"
                    :symbol="symbol"
                    @refresh="() => fetchOrders('open')"
                    @cancel="cancelOrder"
                />
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
</style>
