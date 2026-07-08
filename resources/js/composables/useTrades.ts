import { echo } from '@laravel/echo-vue';
import { ref, onUnmounted } from 'vue';

interface Trade {
    price: string;
    amount: string;
    total: string;
    side: 'buy' | 'sell';
    time: string;
}

export function useTrades(symbol: string) {
    const trades = ref<Trade[]>([]);
    const loading = ref(true);
    const error = ref<string | null>(null);

    /**
     * Fetch recent trades from API
     */
    async function fetchTrades() {
        try {
            loading.value = true;
            error.value = null;

            // Symbol format: BTCUSDT (no slash needed)
            const response = await fetch(`/api/trades/${symbol}?limit=50`);

            if (!response.ok) {
                throw new Error('Failed to fetch trades');
            }

            const data = await response.json();
            console.log('Trades data:', data); // Debug log
            trades.value = data.data || data; // Handle Laravel Resource response
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unknown error';
            console.error('Error fetching trades:', err);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Subscribe to real-time trade updates
     */
    function subscribeToTrades() {
        try {
            const echoInstance = echo();

            if (!echoInstance) {
                console.warn(
                    'Echo not initialized yet, skipping trades subscriptions',
                );

                return;
            }

            echoInstance
                .channel(`trades.${symbol}`)
                .listen('.trade.executed', (event: any) => {
                    console.log('New trade:', event.trade);

                    // Add new trade to the top
                    trades.value.unshift(event.trade);

                    // Keep only last 50 trades
                    if (trades.value.length > 50) {
                        trades.value.pop();
                    }
                });
        } catch (error) {
            console.error('Error subscribing to trades:', error);
        }
    }

    /**
     * Unsubscribe from trade updates
     */
    function unsubscribe() {
        try {
            const echoInstance = echo();

            if (echoInstance) {
                echoInstance.leave(`trades.${symbol}`);
            }
        } catch (error) {
            console.error('Error unsubscribing from trades:', error);
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        unsubscribe();
    });

    return {
        trades,
        loading,
        error,
        fetchTrades,
        subscribeToTrades,
        unsubscribe,
    };
}
