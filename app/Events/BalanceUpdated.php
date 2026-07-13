<?php

namespace App\Events;

use App\Models\Asset;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BalanceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Asset $asset
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->asset->user_id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'balance.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'asset' => [
                'symbol' => $this->asset->symbol,
                'quantity' => $this->asset->quantity,
                'locked_quantity' => $this->asset->locked_quantity,
                'available' => $this->asset->quantity,
                'total' => bcadd($this->asset->quantity, $this->asset->locked_quantity, 8),
            ],
        ];
    }
}
