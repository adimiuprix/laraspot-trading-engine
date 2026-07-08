<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'symbol' => $this->symbol,
            'amount' => $this->amount,
            'locked_amount' => $this->locked_amount,
            'available' => $this->amount,
            'total' => bcadd($this->amount, $this->locked_amount, 8),
        ];
    }
}
