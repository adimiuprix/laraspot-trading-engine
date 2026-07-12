import { echo } from '@laravel/echo-vue';
import { ref, onUnmounted } from 'vue';

export interface Kline {
    time: number; // Unix timestamp in seconds
    open: number;
    high: number;
    low: number;
    close: number;
    volume: number;
}

export function useKlines(symbol: string, interval: string = '1m') {
    const klines = ref<Kline[]>([]);
    const loading = ref(true); // True on initial load
    const error = ref<string | null>(null);

    // Debounce timer for refresh
    let refreshTimer: ReturnType<typeof setTimeout> | null = null;
    const DEBOUNCE_DELAY = 300; // 300ms debounce

    /**
     * Fetch historical klines from API
     */
    async function fetchKlines(limit: number = 500, isInitial = false): Promise<void> {
        try {
            if (isInitial) {
                loading.value = true;
            }
            error.value = null;

            // Symbol format: BTCUSDT (no slash needed)
            const response = await fetch(
                `/api/klines/${symbol}?interval=${interval}&limit=${limit}`,
            );

            if (!response.ok) {
                throw new Error('Failed to fetch klines');
            }

            const data = await response.json();

            // Convert Binance-compatible API format to chart format
            // API returns: [["timestamp", "open", "high", "low", "close", "volume", ...], ...]
            // All values are strings, we need to parse them for the chart
            klines.value = data.map((k: string[]) => ({
                time: parseInt(k[0], 10), // Parse timestamp string to number
                open: parseFloat(k[1]), // Parse price string to number
                high: parseFloat(k[2]), // Parse price string to number
                low: parseFloat(k[3]), // Parse price string to number
                close: parseFloat(k[4]), // Parse price string to number
                volume: parseFloat(k[5]), // Parse volume string to number
            }));

            if (isInitial) {
                console.log(
                    `📊 Loaded ${klines.value.length} klines for ${symbol}`,
                );
            }
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unknown error';
            console.error('Error fetching klines:', err);
        } finally {
            if (isInitial) {
                loading.value = false;
            }
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
            fetchKlines(500, false); // Not initial load
            refreshTimer = null;
        }, DEBOUNCE_DELAY);
    }

    /**
     * Subscribe to real-time kline updates via WebSocket
     */
    function subscribeToUpdates(onUpdate?: () => void): void {
        try {
            const echoInstance = echo();

            if (!echoInstance) {
                console.warn(
                    'Echo not initialized yet, skipping kline subscriptions',
                );
                return;
            }

            // Channel format: kline.BTCUSDT.1m
            const channelName = `kline.${symbol}.${interval}`;

            console.log(`🎧 Subscribing to ${channelName}...`);

            echoInstance
                .channel(channelName)
                .listen('.kline.updated', () => {
                    // Use debounced refresh to prevent multiple rapid fetches
                    debouncedRefresh();

                    if (onUpdate) {
                        onUpdate();
                    }
                });
        } catch (error) {
            console.error('Error subscribing to kline updates:', error);
        }
    }

    /**
     * Unsubscribe from kline updates
     */
    function unsubscribe(): void {
        // Clear any pending refresh timer
        if (refreshTimer) {
            clearTimeout(refreshTimer);
            refreshTimer = null;
        }

        try {
            const echoInstance = echo();

            if (echoInstance) {
                const channelName = `kline.${symbol}.${interval}`;
                echoInstance.leave(channelName);
                console.log(`👋 Unsubscribed from ${channelName}`);
            }
        } catch (error) {
            console.error('Error unsubscribing from kline updates:', error);
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        unsubscribe();
    });

    return {
        klines,
        loading,
        error,
        fetchKlines,
        subscribeToUpdates,
        unsubscribe,
    };
}