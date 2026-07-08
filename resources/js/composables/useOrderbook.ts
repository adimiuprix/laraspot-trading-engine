import { ref, onMounted, onUnmounted } from 'vue';
import { useEcho } from '@laravel/echo-vue';

export function useOrderbook(symbol: string) {
    const echo = useEcho();
    const orderbook = ref<{
        bids: Array<{ price: string; amount: string; total: string }>;
        asks: Array<{ price: string; amount: string; total: string }>;
        last_price: string;
        spread: string;
    }>({
        bids: [],
        asks: [],
        last_price: '0',
        spread: '0',
    });
    const loading = ref(true);
    const error = ref<string | null>(null);

    /**
     * Fetch orderbook from API
     */
    async function fetchOrderbook() {
        try {
            loading.value = true;
            error.value = null;

            // Encode symbol untuk handle slash di URL (BTC/USDT -> BTC%2FUSDT)
            const encodedSymbol = encodeURIComponent(symbol);
            const response = await fetch(`/api/orderbook/${encodedSymbol}?depth=20`);
            
            if (!response.ok) {
                throw new Error('Failed to fetch orderbook');
            }

            const data = await response.json();
            console.log('Orderbook data:', data); // Debug log
            orderbook.value = data;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unknown error';
            console.error('Error fetching orderbook:', err);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Subscribe to real-time orderbook updates
     */
    function subscribeToUpdates() {
        echo
            .channel(`orderbook.${symbol}`)
            .listen('.orderbook.updated', (event: any) => {
                console.log('Orderbook updated:', event);
                // Refresh orderbook when update received
                fetchOrderbook();
            });
    }

    /**
     * Unsubscribe from updates
     */
    function unsubscribe() {
        echo.leave(`orderbook.${symbol}`);
    }

    return {
        orderbook,
        loading,
        error,
        fetchOrderbook,
        subscribeToUpdates,
        unsubscribe,
    };
}
