<?php

namespace App\Services\Trading;

use App\DTOs\CancelOrderDTO;
use App\DTOs\PlaceLimitOrderDTO;
use App\DTOs\PlaceMarketOrderDTO;
use App\Events\BalanceUpdated;
use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Exceptions\InsufficientLiquidityException;
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
        private OrderMatchingEngine $matchingEngine,
        private MarketOrderValidator $marketOrderValidator
    ) {}

    /**
     * Place a new order.
     */
    public function placeOrder(PlaceLimitOrderDTO $dto): Order
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
                'type' => 'limit',
                'price' => $dto->price,
                'amount' => $dto->amount,
                'filled_amount' => '0',
                'remaining_amount' => $dto->amount,
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

            // Unlock the locked amount for REMAINING order only
            if ($order->isBuyOrder()) {
                // Unlock USDT (quote) for remaining amount
                $remainingTotal = bcmul($order->price, $order->remaining_amount, 8);
                $this->assetService->unlockAmount($dto->user, $quote, $remainingTotal);

                // Broadcast balance update
                $quoteAsset = $this->assetService->getOrCreateAsset($dto->user, $quote);
                event(new BalanceUpdated($quoteAsset));
            } else {
                // Unlock BTC (base) for remaining amount
                $this->assetService->unlockAmount($dto->user, $base, $order->remaining_amount);

                // Broadcast balance update
                $baseAsset = $this->assetService->getOrCreateAsset($dto->user, $base);
                event(new BalanceUpdated($baseAsset));
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

    /**
     * Place a market order (instant execution).
     */
    public function placeMarketOrder(PlaceMarketOrderDTO $dto): Order
    {
        return DB::transaction(function () use ($dto) {
            // 1. Get available orders from orderbook
            $availableOrders = $this->orderRepository->getBestOrdersForMarketExecution(
                $dto->symbol,
                $dto->side,
                limit: config('trading.market_order.max_orders_to_fetch', 50)
            );

            // 2. Validate liquidity and slippage (throws exception if fails)
            $validation = $this->marketOrderValidator->validate($dto, $availableOrders);

            // 3. Estimate cost for balance check
            $estimatedCost = $this->estimateMarketOrderCost($availableOrders, $dto->amount);

            // 4. Check balance
            if ($dto->isBuyOrder()) {
                // Buy order: check quote asset (USDT)
                $quoteAsset = $dto->getQuoteAsset();

                if (! $this->assetService->hasSufficientBalance($dto->user, $quoteAsset, $estimatedCost)) {
                    throw new Exception(
                        "Insufficient balance. Required: {$estimatedCost} {$quoteAsset}"
                    );
                }
            } else {
                // Sell order: check base asset (BTC)
                $baseAsset = $dto->getBaseAsset();

                if (! $this->assetService->hasSufficientBalance($dto->user, $baseAsset, $dto->amount)) {
                    throw new Exception(
                        "Insufficient balance. Required: {$dto->amount} {$baseAsset}"
                    );
                }
            }

            // 5. Execute market order matching
            $result = $this->matchingEngine->executeMarketOrder($dto, $availableOrders);

            // 6. Create market order record
            $order = $this->orderRepository->create([
                'user_id' => $dto->user->id,
                'symbol' => $dto->symbol,
                'side' => $dto->side,
                'type' => 'market',
                'price' => $result->averagePrice,
                'amount' => $dto->amount,
                'filled_amount' => $result->filledAmount,
                'remaining_amount' => '0',
                'status' => 'filled',
            ]);

            // 7. Handle balance for market order user
            // Balance for limit order users already handled in executeMarketOrder
            // Here we handle market order user's balance
            [$base, $quote] = $this->parseSymbol($dto->symbol);

            if ($dto->isBuyOrder()) {
                // Market buy: deduct USDT, add BTC
                $quoteAsset = $this->assetService->getUserAsset($dto->user, $quote);
                if ($quoteAsset) {
                    $quoteAsset->amount = bcsub($quoteAsset->amount, $result->totalCost, 8);
                    $quoteAsset->save();
                    event(new BalanceUpdated($quoteAsset));
                }

                $baseAsset = $this->assetService->getOrCreateAsset($dto->user, $base);
                $this->assetService->addAmount($dto->user, $base, $result->filledAmount);
                event(new BalanceUpdated($baseAsset->fresh()));
            } else {
                // Market sell: deduct BTC, add USDT
                $baseAsset = $this->assetService->getUserAsset($dto->user, $base);
                if ($baseAsset) {
                    $baseAsset->amount = bcsub($baseAsset->amount, $result->filledAmount, 8);
                    $baseAsset->save();
                    event(new BalanceUpdated($baseAsset));
                }

                $quoteAsset = $this->assetService->getOrCreateAsset($dto->user, $quote);
                $this->assetService->addAmount($dto->user, $quote, $result->totalCost);
                event(new BalanceUpdated($quoteAsset->fresh()));
            }

            // 8. Broadcast events
            event(new OrderPlaced($order));
            event(new OrderStatusChanged($order));

            return $order;
        });
    }

    /**
     * Estimate the cost of a market order.
     */
    private function estimateMarketOrderCost(Collection $orders, string $amount): string
    {
        $remaining = $amount;
        $totalCost = '0';

        foreach ($orders as $order) {
            if (bccomp($remaining, '0', 8) <= 0) {
                break;
            }

            $fillAmount = bccomp($remaining, $order->remaining_amount, 8) <= 0
                ? $remaining
                : $order->remaining_amount;

            $totalCost = bcadd(
                $totalCost,
                bcmul($order->price, $fillAmount, 8),
                8
            );

            $remaining = bcsub($remaining, $fillAmount, 8);
        }

        return $totalCost;
    }
}
