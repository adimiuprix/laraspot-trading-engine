<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $buyer_order_id
 * @property int $seller_order_id
 * @property int $buyer_id
 * @property int $seller_id
 * @property string $price
 * @property string $amount
 * @property string $commission
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $buyerOrder
 * @property-read Order $sellerOrder
 * @property-read User $buyer
 * @property-read User $seller
 */
#[Fillable(['buyer_order_id', 'seller_order_id', 'buyer_id', 'seller_id', 'price', 'amount', 'commission'])]
class Trade extends Model
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
            'commission' => 'decimal:8',
        ];
    }

    /**
     * Get the buyer's order.
     */
    public function buyerOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'buyer_order_id');
    }

    /**
     * Get the seller's order.
     */
    public function sellerOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'seller_order_id');
    }

    /**
     * Get the buyer user.
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Get the seller user.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * Scope to filter trades by symbol.
     */
    public function scopeForSymbol(Builder $query, string $symbol): void
    {
        $query->whereHas('buyerOrder', fn ($q) => $q->where('symbol', $symbol));
    }

    /**
     * Scope to get recent trades ordered by latest first.
     */
    public function scopeRecent(Builder $query, int $limit = 50): void
    {
        $query->orderBy('created_at', 'desc')
            ->limit($limit);
    }

    /**
     * Scope to get trades for a specific user (as buyer or seller).
     */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where(function ($q) use ($userId) {
            $q->whereHas('buyerOrder', fn ($subQ) => $subQ->where('user_id', $userId))
                ->orWhereHas('sellerOrder', fn ($subQ) => $subQ->where('user_id', $userId));
        });
    }

    /**
     * Get the total value of the trade (price * amount).
     */
    public function getTotalValueAttribute(): string
    {
        return bcmul($this->price, $this->amount, 8);
    }

    /**
     * Get the symbol from the buyer's order.
     */
    public function getSymbolAttribute(): string
    {
        return $this->buyerOrder->symbol;
    }
}
