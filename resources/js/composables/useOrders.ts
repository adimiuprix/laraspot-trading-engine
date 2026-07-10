import { echo } from '@laravel/echo-vue';
import { ref, onUnmounted } from 'vue';

interface Order {
    id: number;
    symbol: string;
    side: 'buy' | 'sell';
    type?: 'limit' | 'market';
    price: string;
    quantity: string;
    filled_quantity?: string;
    remaining_quantity?: string;
    total: string;
    status: 'open' | 'partially_filled' | 'filled' | 'cancelled';
    created_at: string;
}

interface PlaceOrderData {
    symbol: string;
    side: 'buy' | 'sell';
    type?: 'limit' | 'market';
    price: string;
    quantity: string;
}

export function useOrders(userId: number) {
    const orders = ref<Order[]>([]);
    const loading = ref(false);
    const processing = ref(false); // For place/cancel operations
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
     * Fetch active orders (open + partially_filled)
     */
    async function fetchActiveOrders() {
        try {
            loading.value = true;
            error.value = null;

            const response = await fetch('/api/orders', {
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
            const allOrders = data.data || data;

            // Filter to only show active orders (open or partially_filled)
            orders.value = allOrders.filter(
                (order: Order) =>
                    order.status === 'open' ||
                    order.status === 'partially_filled',
            );
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
            processing.value = true; // Use processing instead of loading
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
            processing.value = false;
        }
    }

    /**
     * Cancel an order
     */
    async function cancelOrder(orderId: number) {
        try {
            processing.value = true; // Use processing instead of loading
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
            processing.value = false;
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
                        if (
                            event.order.status === 'cancelled' ||
                            event.order.status === 'filled'
                        ) {
                            // Remove cancelled and fully filled orders from active orders list
                            orders.value.splice(index, 1);
                        } else {
                            // Update order (including partially_filled)
                            orders.value[index] = {
                                ...orders.value[index],
                                ...event.order,
                            };
                        }
                    } else {
                        // Order doesn't exist in list
                        // Add if it's an active order (open or partially_filled)
                        if (
                            event.order.status === 'open' ||
                            event.order.status === 'partially_filled'
                        ) {
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
        processing,
        error,
        fetchOrders,
        fetchActiveOrders,
        placeOrder,
        cancelOrder,
        subscribeToOrderUpdates,
        unsubscribe,
    };
}
