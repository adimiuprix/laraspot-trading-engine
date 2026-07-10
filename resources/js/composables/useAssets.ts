import { echo } from '@laravel/echo-vue';
import { ref, onUnmounted } from 'vue';

interface Asset {
    symbol: string;
    quantity: string;
    locked_quantity: string;
    available: string;
    total: string;
}

export function useAssets(userId: number) {
    const assets = ref<Record<string, Asset>>({});
    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * Fetch user's assets
     */
    async function fetchAssets() {
        try {
            loading.value = true;
            error.value = null;

            const response = await fetch('/api/assets', {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include', // Important: Include cookies for session auth
            });

            if (!response.ok) {
                throw new Error(
                    `Failed to fetch assets: ${response.status} ${response.statusText}`,
                );
            }

            const data = await response.json();
            console.log('Assets API Response:', data);

            const assetsList = data.data || data;
            console.log('Assets List:', assetsList);

            // Convert array to object keyed by symbol
            assets.value = assetsList.reduce(
                (acc: Record<string, Asset>, asset: Asset) => {
                    acc[asset.symbol] = asset;

                    return acc;
                },
                {},
            );

            console.log('Assets Object:', assets.value);
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unknown error';
            console.error('Error fetching assets:', err);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Get specific asset balance
     */
    function getAsset(symbol: string): Asset | null {
        return assets.value[symbol] || null;
    }

    /**
     * Get available balance for a symbol
     */
    function getAvailableBalance(symbol: string): string {
        const asset = assets.value[symbol];

        return asset?.available || '0';
    }

    /**
     * Subscribe to balance updates
     */
    function subscribeToBalanceUpdates() {
        try {
            const echoInstance = echo();

            if (!echoInstance) {
                console.warn(
                    'Echo not initialized yet, skipping balance subscriptions',
                );

                return;
            }

            echoInstance
                .private(`user.${userId}`)
                .listen('.balance.updated', (event: any) => {
                    console.log('Balance updated:', event.asset);

                    // Update specific asset
                    assets.value[event.asset.symbol] = event.asset;
                });
        } catch (error) {
            console.error('Error subscribing to balance updates:', error);
        }
    }

    /**
     * Unsubscribe from updates
     */
    function unsubscribe() {
        try {
            const echoInstance = echo();

            if (echoInstance) {
                echoInstance.leave(`user.${userId}`);
            }
        } catch (error) {
            console.error('Error unsubscribing from balance updates:', error);
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        unsubscribe();
    });

    return {
        assets,
        loading,
        error,
        fetchAssets,
        getAsset,
        getAvailableBalance,
        subscribeToBalanceUpdates,
        unsubscribe,
    };
}
