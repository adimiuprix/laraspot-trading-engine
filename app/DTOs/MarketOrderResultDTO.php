<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

class MarketOrderResultDTO
{
    public function __construct(
        public readonly string $averagePrice,
        public readonly string $filledAmount,
        public readonly string $totalCost,
        public readonly Collection $trades,
        public readonly int $tradesCount,
    ) {}
}
