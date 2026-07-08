import { ref } from 'vue';
import { useEcho } from '@laravel/echo-vue';
import { router } from '@inertiajs/vue3';

interface Order {
    id: number;
    symbol: string;
    side: 'buy' | 'sell';
    price: string;
    amount: string;
    total: string;
    status: 'open' | 'filled' | 'cancelled';
    created_at: string;
}

interface PlaceOrderData {
    symbol: string;
    side: 'buy' | 'sell';
    price: string;
    amount: string;
}

export function useOrders(userId: number) {
    const echo = useEcho();
    const orders = ref<Order[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * Fetch user's orders
     */
    async function fetchOrders(status?: string) {
        try {
            loading.value = true;
            error.value = null;

            const url = status ? `/api/orders?status=${status}` : '/api/orders';
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                },
            });
            
            if (!response.ok) {
                throw new Error('Failed to fetch orders');
            }

            const data = await response.json();
            orders.value = data.data || data;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unknown error';
            console.error('Error fetching orders:', err);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Place a new order
     */
    async function placeOrder(orderData: PlaceOrderData) {
        try {
            loading.value = true;
            error.value = null;

            const response = await fetch('/api/orders', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify(orderData),
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to place order');
            }

            const data = await response.json();
            
            // Refresh orders list
            await fetchOrders();
            
            return data.data || data;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unknown error';
            console.error('Error placing order:', err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Cancel an order
     */
    async function cancelOrder(orderId: number) {
        try {
            loading.value = true;
            error.value = null;

            const response = await fetch(`/api/orders/${orderId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to cancel order');
            }

            // Remove from local state
            orders.value = orders.value.filter(o => o.id !== orderId);

            return true;
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Unknown error';
            console.error('Error cancelling order:', err);
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Subscribe to user's order updates
     */
    function subscribeToOrderUpdates() {
        echo
            .private(`user.${userId}`)
            .listen('.order.status.changed', (event: any) => {
                console.log('Order status changed:', event.order);
                
                // Update order in list
                const index = orders.value.findIndex(o => o.id === event.order.id);
                if (index !== -1) {
                    orders.value[index] = { ...orders.value[index], ...event.order };
                } else {
                    // Add new order if not exists
                    orders.value.unshift(event.order);
                }
            });
    }

    /**
     * Unsubscribe from updates
     */
    function unsubscribe() {
        echo.leave(`user.${userId}`);
    }

    return {
        orders,
        loading,
        error,
        fetchOrders,
        placeOrder,
        cancelOrder,
        subscribeToOrderUpdates,
        unsubscribe,
    };
}
