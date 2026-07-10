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
 * @property string $type (limit/market)
 * @property string $price
 * @property string $quantity
 * @property string $filled_quantity
 * @property string $remaining_quantity
 * @property string $status (open/partially_filled/filled/cancelled)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Trade> $buyTrades
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Trade> $sellTrades
 */
#[Fillable(['user_id', 'symbol', 'side', 'type', 'price', 'quantity', 'filled_quantity', 'remaining_quantity', 'status'])]
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
            'quantity' => 'decimal:8',
            'filled_quantity' => 'decimal:8',
            'remaining_quantity' => 'decimal:8',
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
        $query->whereIn('status', ['open', 'partially_filled']);
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
            ->whereIn('status', ['open', 'partially_filled'])
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
     * Orders can be cancelled if they are open or partially filled.
     * Filled orders cannot be cancelled (already executed).
     * Cancelled orders cannot be cancelled again.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['open', 'partially_filled']);
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
     * Check if order is a limit order.
     */
    public function isLimitOrder(): bool
    {
        return $this->type === 'limit';
    }

    /**
     * Check if order is a market order.
     */
    public function isMarketOrder(): bool
    {
        return $this->type === 'market';
    }

    /**
     * Get the total value of the order (price * remaining_quantity for active orders).
     */
    public function getTotalValueAttribute(): string
    {
        // For active orders (open/partially_filled), use remaining_quantity
        if (in_array($this->status, ['open', 'partially_filled'])) {
            return bcmul($this->price, $this->remaining_quantity, 8);
        }

        // For filled/cancelled, use original quantity
        return bcmul($this->price, $this->quantity, 8);
    }

    /**
     * Check if order is fully filled.
     */
    public function isFullyFilled(): bool
    {
        return bccomp($this->filled_quantity, $this->quantity, 8) === 0;
    }

    /**
     * Check if order is partially filled.
     */
    public function isPartiallyFilled(): bool
    {
        return bccomp($this->filled_quantity, '0', 8) > 0
            && bccomp($this->filled_quantity, $this->quantity, 8) < 0;
    }

    /**
     * Get fill percentage (0-100).
     */
    public function getFillPercentage(): string
    {
        if (bccomp($this->quantity, '0', 8) === 0) {
            return '0';
        }

        return bcdiv(bcmul($this->filled_quantity, '100', 8), $this->quantity, 2);
    }

    /**
     * Add fill quantity and update status.
     */
    public function addFill(string $fillquantity): void
    {
        $this->filled_quantity = bcadd($this->filled_quantity, $fillquantity, 8);
        $this->remaining_quantity = bcsub($this->quantity, $this->filled_quantity, 8);

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
        $this->filled_quantity = $this->quantity;
        $this->remaining_quantity = '0';
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
