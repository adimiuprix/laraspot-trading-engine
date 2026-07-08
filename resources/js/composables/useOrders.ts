import { echo } from '@laravel/echo-vue';
import { ref, onUnmounted } from 'vue';

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
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                throw new Error(
                    `Failed to fetch orders: ${response.status} ${response.statusText}`,
                );
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
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                credentials: 'include',
                body: JSON.stringify(orderData),
            });

            if (!response.ok) {
                const errorData = await response.json();

                throw new Error(errorData.message || 'Failed to place order');
            }

            const data = await response.json();

            // Don't refresh orders list here - let the WebSocket event handle it
            // This prevents duplicate orders from appearing

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
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                credentials: 'include',
            });

            if (!response.ok) {
                const errorData = await response.json();

                throw new Error(errorData.message || 'Failed to cancel order');
            }

            // Don't remove from local state here - let the WebSocket event handle it
            // This prevents race conditions and ensures consistency

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
        try {
            const echoInstance = echo();

            if (!echoInstance) {
                console.warn(
                    'Echo not initialized yet, skipping order subscriptions',
                );

                return;
            }

            echoInstance
                .private(`user.${userId}`)
                .listen('.order.status.changed', (event: any) => {
                    console.log('Order status changed:', event.order);

                    const index = orders.value.findIndex(
                        (o) => o.id === event.order.id,
                    );

                    if (index !== -1) {
                        // Order exists in list
                        if (event.order.status === 'cancelled') {
                            // Remove cancelled orders from list
                            orders.value.splice(index, 1);
                        } else {
                            // Update order status
                            orders.value[index] = {
                                ...orders.value[index],
                                ...event.order,
                            };
                        }
                    } else {
                        // Order doesn't exist in list
                        // Only add if it's a new open order (not cancelled or filled)
                        if (event.order.status === 'open') {
                            orders.value.unshift(event.order);
                        }
                    }
                });
        } catch (error) {
            console.error('Error subscribing to order updates:', error);
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
            console.error('Error unsubscribing from order updates:', error);
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        unsubscribe();
    });

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
