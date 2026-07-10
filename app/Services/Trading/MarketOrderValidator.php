<?php

namespace App\Services\Trading;

use App\DTOs\PlaceMarketOrderDTO;
use App\Exceptions\InsufficientLiquidityException;
use App\Exceptions\ExcessiveSlippageException;
use Illuminate\Support\Collection;

class MarketOrderValidator
{
    /**
     * Validate if market order can be executed safely.
     */
    public function validate(
        PlaceMarketOrderDTO $dto,
        Collection $availableOrders
    ): MarketOrderValidationResult {
        $totalAvailable = $availableOrders->sum('remaining_quantity');

        // 1. Check absolute liquidity
        if (bccomp($totalAvailable, $dto->quantity, 8) < 0) {
            throw new InsufficientLiquidityException(
                "Insufficient liquidity. Requested: {$dto->quantity}, Available: {$totalAvailable}"
            );
        }

        // 2. Calculate price impact
        $priceImpact = $this->calculatePriceImpact($availableOrders, $dto->quantity);
        
        if ($priceImpact > config('trading.market_order.max_price_impact_percent', 10)) {
            throw new ExcessiveSlippageException(
                "Price impact too high: {$priceImpact}%. " .
                "This order will significantly move the market. " .
                "Consider splitting into smaller orders."
            );
        }

        // 3. Calculate slippage
        $bestPrice = $availableOrders->first()->price;
        $averagePrice = $this->estimateAveragePrice($availableOrders, $dto->quantity);
        $slippage = $this->calculateSlippage($bestPrice, $averagePrice);

        if ($slippage > config('trading.market_order.max_slippage_percent', 5)) {
            throw new ExcessiveSlippageException(
                "Slippage too high: {$slippage}%. " .
                "Best price: {$bestPrice}, Expected avg: {$averagePrice}"
            );
        }

        return new MarketOrderValidationResult(
            canExecute: true,
            totalAvailable: $totalAvailable,
            estimatedAveragePrice: $averagePrice,
            slippage: $slippage,
            priceImpact: $priceImpact,
            ordersToFill: $this->getOrdersToFill($availableOrders, $dto->quantity)
        );
    }

    /**
     * Calculate price impact (how much order moves the market).
     */
    private function calculatePriceImpact(Collection $orders, string $quantity): float
    {
        if ($orders->isEmpty()) {
            return 0;
        }

        $bestPrice = $orders->first()->price;
        $worstPrice = $this->getWorstPriceForquantity($orders, $quantity);

        $impact = bcdiv(
            bcsub($worstPrice, $bestPrice, 8),
            $bestPrice,
            8
        );

        return (float) bcmul($impact, '100', 2);
    }

    /**
     * Get the worst price that will be paid when filling this quantity.
     */
    private function getWorstPriceForquantity(Collection $orders, string $quantity): string
    {
        $remaining = $quantity;
        $worstPrice = '0';

        foreach ($orders as $order) {
            if (bccomp($remaining, '0', 8) <= 0) {
                break;
            }

            $worstPrice = $order->price;
            $fillquantity = bccomp($remaining, $order->remaining_quantity, 8) <= 0
                ? $remaining
                : $order->remaining_quantity;

            $remaining = bcsub($remaining, $fillquantity, 8);
        }

        return $worstPrice;
    }

    /**
     * Estimate average execution price.
     */
    private function estimateAveragePrice(Collection $orders, string $quantity): string
    {
        $remaining = $quantity;
        $totalCost = '0';

        foreach ($orders as $order) {
            if (bccomp($remaining, '0', 8) <= 0) {
                break;
            }

            $fillquantity = bccomp($remaining, $order->remaining_quantity, 8) <= 0
                ? $remaining
                : $order->remaining_quantity;

            $totalCost = bcadd(
                $totalCost,
                bcmul($order->price, $fillquantity, 8),
                8
            );

            $remaining = bcsub($remaining, $fillquantity, 8);
        }

        return bcdiv($totalCost, $quantity, 8);
    }

    /**
     * Calculate slippage percentage.
     */
    private function calculateSlippage(string $bestPrice, string $averagePrice): float
    {
        $diff = bcsub($averagePrice, $bestPrice, 8);
        $slippage = bcdiv($diff, $bestPrice, 8);

        return (float) bcmul($slippage, '100', 2);
    }

    /**
     * Get list of orders that will be filled.
     */
    private function getOrdersToFill(Collection $orders, string $quantity): Collection
    {
        $remaining = $quantity;
        $ordersToFill = collect();

        foreach ($orders as $order) {
            if (bccomp($remaining, '0', 8) <= 0) {
                break;
            }

            $fillquantity = bccomp($remaining, $order->remaining_quantity, 8) <= 0
                ? $remaining
                : $order->remaining_quantity;

            $ordersToFill->push([
                'order_id' => $order->id,
                'price' => $order->price,
                'fill_quantity' => $fillquantity,
            ]);

            $remaining = bcsub($remaining, $fillquantity, 8);
        }

        return $ordersToFill;
    }
}

class MarketOrderValidationResult
{
    public function __construct(
        public readonly bool $canExecute,
        public readonly string $totalAvailable,
        public readonly string $estimatedAveragePrice,
        public readonly float $slippage,
        public readonly float $priceImpact,
        public readonly Collection $ordersToFill,
    ) {}
}
