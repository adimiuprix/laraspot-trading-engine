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
        $totalAvailable = $availableOrders->sum('remaining_amount');

        // 1. Check absolute liquidity
        if (bccomp($totalAvailable, $dto->amount, 8) < 0) {
            throw new InsufficientLiquidityException(
                "Insufficient liquidity. Requested: {$dto->amount}, Available: {$totalAvailable}"
            );
        }

        // 2. Calculate price impact
        $priceImpact = $this->calculatePriceImpact($availableOrders, $dto->amount);
        
        if ($priceImpact > config('trading.market_order.max_price_impact_percent', 10)) {
            throw new ExcessiveSlippageException(
                "Price impact too high: {$priceImpact}%. " .
                "This order will significantly move the market. " .
                "Consider splitting into smaller orders."
            );
        }

        // 3. Calculate slippage
        $bestPrice = $availableOrders->first()->price;
        $averagePrice = $this->estimateAveragePrice($availableOrders, $dto->amount);
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
            ordersToFill: $this->getOrdersToFill($availableOrders, $dto->amount)
        );
    }

    /**
     * Calculate price impact (how much order moves the market).
     */
    private function calculatePriceImpact(Collection $orders, string $amount): float
    {
        if ($orders->isEmpty()) {
            return 0;
        }

        $bestPrice = $orders->first()->price;
        $worstPrice = $this->getWorstPriceForAmount($orders, $amount);

        $impact = bcdiv(
            bcsub($worstPrice, $bestPrice, 8),
            $bestPrice,
            8
        );

        return (float) bcmul($impact, '100', 2);
    }

    /**
     * Get the worst price that will be paid when filling this amount.
     */
    private function getWorstPriceForAmount(Collection $orders, string $amount): string
    {
        $remaining = $amount;
        $worstPrice = '0';

        foreach ($orders as $order) {
            if (bccomp($remaining, '0', 8) <= 0) {
                break;
            }

            $worstPrice = $order->price;
            $fillAmount = bccomp($remaining, $order->remaining_amount, 8) <= 0
                ? $remaining
                : $order->remaining_amount;

            $remaining = bcsub($remaining, $fillAmount, 8);
        }

        return $worstPrice;
    }

    /**
     * Estimate average execution price.
     */
    private function estimateAveragePrice(Collection $orders, string $amount): string
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

        return bcdiv($totalCost, $amount, 8);
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
    private function getOrdersToFill(Collection $orders, string $amount): Collection
    {
        $remaining = $amount;
        $ordersToFill = collect();

        foreach ($orders as $order) {
            if (bccomp($remaining, '0', 8) <= 0) {
                break;
            }

            $fillAmount = bccomp($remaining, $order->remaining_amount, 8) <= 0
                ? $remaining
                : $order->remaining_amount;

            $ordersToFill->push([
                'order_id' => $order->id,
                'price' => $order->price,
                'fill_amount' => $fillAmount,
            ]);

            $remaining = bcsub($remaining, $fillAmount, 8);
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
