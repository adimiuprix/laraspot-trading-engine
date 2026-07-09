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

interface KlineUpdateEvent {
    e: string; // Event type
    s: string; // Symbol
    k: {
        t: number; // Open time (milliseconds)
        T: number; // Close time (milliseconds)
        s: string; // Symbol
        i: string; // Interval
        o: string; // Open
        h: string; // High
        l: string; // Low
        c: string; // Close
        v: string; // Volume
        q: string; // Quote volume
        n: number; // Trades count
        x: boolean; // Is closed?
    };
}

export function useKlines(symbol: string, interval: string = '1m') {
    const klines = ref<Kline[]>([]);
    const loading = ref(true); // True on initial load
    const error = ref<string | null>(null);

    /**
     * Fetch historical klines from API
     */
    async function fetchKlines(limit: number = 500): Promise<void> {
        try {
            loading.value = true;
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
            // API returns: [[timestamp, open, high, low, close, volume, ...], ...]
            klines.value = data.map((k: any[]) => ({
                time: k[0], // Open time (seconds)
                open: parseFloat(k[1]),
                high: parseFloat(k[2]),
                low: parseFloat(k[3]),
                close: parseFloat(k[4]),
                volume: parseFloat(k[5]),
            }));

            console.log(`📊 Loaded ${klines.value.length} klines for ${symbol}`);
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unknown error';
            console.error('Error fetching klines:', err);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Subscribe to real-time kline updates via WebSocket
     */
    function subscribeToUpdates(
        onUpdate?: (kline: Kline) => void,
    ): void {
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
                .listen('.kline.updated', (event: KlineUpdateEvent) => {
                    console.log('🔥 Kline update received:', event.k);

                    const updatedKline: Kline = {
                        time: Math.floor(event.k.t / 1000), // Convert ms to seconds
                        open: parseFloat(event.k.o),
                        high: parseFloat(event.k.h),
                        low: parseFloat(event.k.l),
                        close: parseFloat(event.k.c),
                        volume: parseFloat(event.k.v),
                    };

                    // Call callback immediately - let chart handle the update
                    // Don't modify klines array here to avoid reactivity glitches
                    if (onUpdate) {
                        onUpdate(updatedKline);
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
