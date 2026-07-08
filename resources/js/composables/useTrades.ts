import { ref } from 'vue';
import { useEcho } from '@laravel/echo-vue';

interface Trade {
    price: string;
    amount: string;
    total: string;
    side: 'buy' | 'sell';
    time: string;
}

export function useTrades(symbol: string) {
    const echo = useEcho();
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

            const response = await fetch(`/api/trades/${symbol}?limit=50`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch trades');
            }

            const data = await response.json();
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
        echo
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
    }

    /**
     * Unsubscribe from trade updates
     */
    function unsubscribe() {
        echo.leave(`trades.${symbol}`);
    }

    return {
        trades,
        loading,
        error,
        fetchTrades,
        subscribeToTrades,
        unsubscribe,
    };
}
