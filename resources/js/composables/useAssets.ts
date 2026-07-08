import { ref } from 'vue';
import { useEcho } from '@laravel/echo-vue';

interface Asset {
    symbol: string;
    amount: string;
    locked_amount: string;
    available: string;
    total: string;
}

export function useAssets(userId: number) {
    const echo = useEcho();
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
                headers: {
                    'Accept': 'application/json',
                },
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch assets');
            }

            const data = await response.json();
            const assetsList = data.data || data;

            // Convert array to object keyed by symbol
            assets.value = assetsList.reduce((acc: Record<string, Asset>, asset: Asset) => {
                acc[asset.symbol] = asset;
                return acc;
            }, {});
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
        echo
            .private(`user.${userId}`)
            .listen('.balance.updated', (event: any) => {
                console.log('Balance updated:', event.asset);
                
                // Update specific asset
                assets.value[event.asset.symbol] = event.asset;
            });
    }

    /**
     * Unsubscribe from updates
     */
    function unsubscribe() {
        echo.leave(`user.${userId}`);
    }

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
