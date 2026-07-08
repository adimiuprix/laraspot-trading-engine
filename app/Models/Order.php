<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $symbol
 * @property string $side (buy/sell)
 * @property string $price
 * @property string $amount
 * @property string $filled_amount
 * @property string $remaining_amount
 * @property string $status (open/partially_filled/filled/cancelled)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Trade> $buyTrades
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Trade> $sellTrades
 */
#[Fillable(['user_id', 'symbol', 'side', 'price', 'amount', 'filled_amount', 'remaining_amount', 'status'])]
class Order extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:8',
            'amount' => 'decimal:8',
            'filled_amount' => 'decimal:8',
            'remaining_amount' => 'decimal:8',
        ];
    }

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get trades where this order is the buyer.
     */
    public function buyTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'buyer_order_id');
    }

    /**
     * Get trades where this order is the seller.
     */
    public function sellTrades(): HasMany
    {
        return $this->hasMany(Trade::class, 'seller_order_id');
    }

    /**
     * Scope to filter open orders.
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', 'open');
    }

    /**
     * Scope to filter by trading pair symbol.
     */
    public function scopeForSymbol(Builder $query, string $symbol): void
    {
        $query->where('symbol', $symbol);
    }

    /**
     * Scope to filter by side (buy/sell).
     */
    public function scopeSide(Builder $query, string $side): void
    {
        $query->where('side', $side);
    }

    /**
     * Scope to get orders that can be matched against a given price.
     * For buy orders: get sell orders at or below the price.
     * For sell orders: get buy orders at or above the price.
     */
    public function scopeMatchable(Builder $query, string $side, string $price): void
    {
        $oppositeSide = $side === 'buy' ? 'sell' : 'buy';

        $query->where('side', $oppositeSide)
            ->where('status', 'open')
            ->where(function ($q) use ($side, $price) {
                if ($side === 'buy') {
                    // Buy order can match sell orders at or below the buy price
                    $q->where('price', '<=', $price);
                } else {
                    // Sell order can match buy orders at or above the sell price
                    $q->where('price', '>=', $price);
                }
            });
    }

    /**
     * Check if order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if order is a buy order.
     */
    public function isBuyOrder(): bool
    {
        return $this->side === 'buy';
    }

    /**
     * Check if order is a sell order.
     */
    public function isSellOrder(): bool
    {
        return $this->side === 'sell';
    }

    /**
     * Get the total value of the order (price * amount).
     */
    public function getTotalValueAttribute(): string
    {
        return bcmul($this->price, $this->amount, 8);
    }

    /**
     * Check if order is fully filled.
     */
    public function isFullyFilled(): bool
    {
        return bccomp($this->filled_amount, $this->amount, 8) === 0;
    }

    /**
     * Check if order is partially filled.
     */
    public function isPartiallyFilled(): bool
    {
        return bccomp($this->filled_amount, '0', 8) > 0
            && bccomp($this->filled_amount, $this->amount, 8) < 0;
    }

    /**
     * Get fill percentage (0-100).
     */
    public function getFillPercentage(): string
    {
        if (bccomp($this->amount, '0', 8) === 0) {
            return '0';
        }

        return bcdiv(bcmul($this->filled_amount, '100', 8), $this->amount, 2);
    }

    /**
     * Add fill amount and update status.
     */
    public function addFill(string $fillAmount): void
    {
        $this->filled_amount = bcadd($this->filled_amount, $fillAmount, 8);
        $this->remaining_amount = bcsub($this->amount, $this->filled_amount, 8);

        if ($this->isFullyFilled()) {
            $this->status = 'filled';
        } elseif ($this->isPartiallyFilled()) {
            $this->status = 'partially_filled';
        }

        $this->save();
    }

    /**
     * Mark order as filled.
     */
    public function markAsFilled(): void
    {
        $this->filled_amount = $this->amount;
        $this->remaining_amount = '0';
        $this->status = 'filled';
        $this->save();
    }

    /**
     * Mark order as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->status = 'cancelled';
        $this->save();
    }
}
