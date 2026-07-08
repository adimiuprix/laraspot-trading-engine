<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'price' => $this->price,
            'amount' => $this->amount,
            'total' => $this->total_value,
            'commission' => $this->commission,
            'side' => $this->buyerOrder->user_id === auth()->id() ? 'buy' : 'sell',
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
