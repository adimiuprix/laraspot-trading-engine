import { echo } from '@laravel/echo-vue';
import { ref, onUnmounted } from 'vue';

export function useOrderbook(symbol: string) {
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

    // Debounce timer for refresh
    let refreshTimer: ReturnType<typeof setTimeout> | null = null;
    const DEBOUNCE_DELAY = 300; // 300ms debounce

    /**
     * Fetch orderbook from API
     */
    async function fetchOrderbook() {
        try {
            loading.value = true;
            error.value = null;

            // Symbol format: BTCUSDT (no slash needed)
            const response = await fetch(`/api/orderbook/${symbol}?depth=20`);

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
     * Debounced refresh function to prevent multiple rapid fetches
     */
    function debouncedRefresh() {
        // Clear existing timer
        if (refreshTimer) {
            clearTimeout(refreshTimer);
        }

        // Set new timer
        refreshTimer = setTimeout(() => {
            console.log('Debounced orderbook refresh triggered');
            fetchOrderbook();
            refreshTimer = null;
        }, DEBOUNCE_DELAY);
    }

    /**
     * Subscribe to real-time orderbook updates
     */
    function subscribeToUpdates() {
        try {
            const echoInstance = echo();

            if (!echoInstance) {
                console.warn(
                    'Echo not initialized yet, skipping orderbook subscriptions',
                );

                return;
            }

            echoInstance
                .channel(`orderbook.${symbol}`)
                .listen('.orderbook.updated', (event: any) => {
                    console.log(
                        'Orderbook update event received:',
                        event.type,
                        event,
                    );
                    // Use debounced refresh to prevent multiple rapid fetches
                    debouncedRefresh();
                });
        } catch (error) {
            console.error('Error subscribing to orderbook updates:', error);
        }
    }

    /**
     * Unsubscribe from updates
     */
    function unsubscribe() {
        // Clear any pending refresh timer
        if (refreshTimer) {
            clearTimeout(refreshTimer);
            refreshTimer = null;
        }

        try {
            const echoInstance = echo();

            if (echoInstance) {
                echoInstance.leave(`orderbook.${symbol}`);
            }
        } catch (error) {
            console.error('Error unsubscribing from orderbook updates:', error);
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        unsubscribe();
    });

    return {
        orderbook,
        loading,
        error,
        fetchOrderbook,
        subscribeToUpdates,
        unsubscribe,
    };
}
