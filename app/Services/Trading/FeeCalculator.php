<?php

namespace App\Services\Trading;

class FeeCalculator
{
    /**
     * Trading fee rate (0.1% = 0.001).
     */
    private float $feeRate = 0.001;

    /**
     * Calculate trading fee.
     */
    public function calculate(string $price, string $quantity): string
    {
        $total = bcmul($price, $quantity, 8);

        return bcmul($total, (string) $this->feeRate, 8);
    }

    /**
     * Get the fee rate.
     */
    public function getFeeRate(): float
    {
        return $this->feeRate;
    }
}
