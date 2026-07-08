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
 * @property string $amount
 * @property string $locked_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'symbol', 'amount', 'locked_amount'])]
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
            'amount' => 'decimal:8',
            'locked_amount' => 'decimal:8',
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
        return (string) $this->amount;
    }

    /**
     * Lock a specific amount for trading.
     */
    public function lockAmount(string $amount): void
    {
        $this->decrement('amount', $amount);
        $this->increment('locked_amount', $amount);
    }

    /**
     * Unlock a specific amount (e.g., when order is cancelled).
     */
    public function unlockAmount(string $amount): void
    {
        $this->decrement('locked_amount', $amount);
        $this->increment('amount', $amount);
    }

    /**
     * Release locked amount after trade execution.
     */
    public function releaseLocked(string $amount): void
    {
        $this->decrement('locked_amount', $amount);
    }

    /**
     * Add amount to balance (e.g., after buying).
     */
    public function addAmount(string $amount): void
    {
        $this->increment('amount', $amount);
    }

    /**
     * Check if user has sufficient available balance.
     */
    public function hasSufficientBalance(string $amount): bool
    {
        return bccomp($this->amount, $amount, 8) >= 0;
    }
}
