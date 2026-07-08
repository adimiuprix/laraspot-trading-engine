<?php

namespace App\DTOs;

class OrderBookQueryDTO
{
    public function __construct(
        public readonly string $symbol,
        public readonly int $depth = 20,
    ) {}

    /**
     * Create DTO from request data.
     */
    public static function fromRequest(string $symbol, ?int $depth = null): self
    {
        return new self(
            symbol: $symbol,
            depth: $depth ?? 20,
        );
    }
}
