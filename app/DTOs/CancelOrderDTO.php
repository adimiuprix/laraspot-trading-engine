<?php

namespace App\DTOs;

use App\Models\User;

class CancelOrderDTO
{
    public function __construct(
        public readonly User $user,
        public readonly int $orderId,
    ) {}

    /**
     * Create DTO from request data.
     */
    public static function fromRequest(User $user, int $orderId): self
    {
        return new self(
            user: $user,
            orderId: $orderId,
        );
    }
}
