<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $symbol
 * @property string $quantity
 * @property string $locked_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'symbol', 'quantity', 'locked_quantity'])]
class Asset extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:8',
            'locked_quantity' => 'decimal:8',
        ];
    }

    /**
     * Get the user that owns the asset.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the available balance (not locked).
     */
    public function getAvailableBalanceAttribute(): string
    {
        return (string) $this->quantity;
    }

    /**
     * Lock a specific quantity for trading.
     */
    public function lockquantity(string $quantity): void
    {
        $this->decrement('quantity', $quantity);
        $this->increment('locked_quantity', $quantity);
    }

    /**
     * Unlock a specific quantity (e.g., when order is cancelled).
     */
    public function unlockquantity(string $quantity): void
    {
        $this->decrement('locked_quantity', $quantity);
        $this->increment('quantity', $quantity);
    }

    /**
     * Release locked quantity after trade execution.
     */
    public function releaseLocked(string $quantity): void
    {
        $this->decrement('locked_quantity', $quantity);
    }

    /**
     * Add quantity to balance (e.g., after buying).
     */
    public function addquantity(string $quantity): void
    {
        $this->increment('quantity', $quantity);
    }

    /**
     * Check if user has sufficient available balance.
     */
    public function hasSufficientBalance(string $quantity): bool
    {
        return bccomp($this->quantity, $quantity, 8) >= 0;
    }
}
