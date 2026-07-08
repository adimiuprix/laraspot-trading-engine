<?php

namespace App\Services\Trading;

use App\DTOs\CancelOrderDTO;
use App\DTOs\PlaceOrderDTO;
use App\Events\BalanceUpdated;
use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\AssetService;
use App\Services\Trading\Concerns\ParsesSymbol;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class OrderService
{
    use ParsesSymbol;

    public function __construct(
        private OrderRepository $orderRepository,
        private AssetService $assetService,
        private OrderMatchingEngine $matchingEngine
    ) {}

    /**
     * Place a new order.
     */
    public function placeOrder(PlaceOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            // Check if user has sufficient balance
            $assetToLock = $dto->getAssetToLock();
            $amountToLock = $dto->getAmountToLock();

            if (! $this->assetService->hasSufficientBalance($dto->user, $assetToLock, $amountToLock)) {
                throw new Exception('Insufficient balance');
            }

            // Lock the required amount
            $this->assetService->lockAmount($dto->user, $assetToLock, $amountToLock);

            // Broadcast balance update
            $asset = $dto->user->getAsset($assetToLock);
            event(new BalanceUpdated($asset));

            // Create order
            $order = $this->orderRepository->create([
                'user_id' => $dto->user->id,
                'symbol' => $dto->symbol,
                'side' => $dto->side,
                'price' => $dto->price,
                'amount' => $dto->amount,
                'status' => 'open',
            ]);

            // Broadcast order placed
            event(new OrderPlaced($order));
            event(new OrderStatusChanged($order));

            // Try to match immediately
            $this->matchingEngine->matchOrder($order);

            return $order->fresh(); // Reload to get updated status
        });
    }

    /**
     * Cancel an existing order.
     */
    public function cancelOrder(CancelOrderDTO $dto): bool
    {
        return DB::transaction(function () use ($dto) {
            $order = $this->orderRepository->findById($dto->orderId);

            if (! $order) {
                throw new Exception('Order not found');
            }

            // Check ownership
            if ($order->user_id !== $dto->user->id) {
                throw new Exception('Unauthorized');
            }

            // Check if order can be cancelled
            if (! $order->canBeCancelled()) {
                throw new Exception('Order cannot be cancelled');
            }

            // Extract base and quote from symbol
            [$base, $quote] = $this->parseSymbol($order->symbol);

            // Unlock the locked amount
            if ($order->isBuyOrder()) {
                // Unlock USDT (quote)
                $amountToUnlock = bcmul($order->price, $order->amount, 8);
                $this->assetService->unlockAmount($dto->user, $quote, $amountToUnlock);

                // Broadcast balance update
                $asset = $this->assetService->getOrCreateAsset($dto->user, $quote);
                event(new BalanceUpdated($asset));
            } else {
                // Unlock BTC (base)
                $this->assetService->unlockAmount($dto->user, $base, $order->amount);

                // Broadcast balance update
                $asset = $this->assetService->getOrCreateAsset($dto->user, $base);
                event(new BalanceUpdated($asset));
            }

            // Update order status
            $order->markAsCancelled();

            // Broadcast order cancelled
            event(new OrderCancelled($order));
            event(new OrderStatusChanged($order));

            return true;
        });
    }

    /**
     * Get user's orders.
     */
    public function getUserOrders(int $userId, ?string $status = null): Collection
    {
        return $this->orderRepository->getUserOrders($userId, $status);
    }

    /**
     * Get order by ID.
     */
    public function getOrderById(int $orderId): ?Order
    {
        return $this->orderRepository->findById($orderId);
    }
}
