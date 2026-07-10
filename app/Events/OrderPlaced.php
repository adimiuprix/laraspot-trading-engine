<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // Replace slash with dash for channel name (Pusher doesn't support slash)
        $channelSymbol = str_replace('/', '-', $this->order->symbol);

        return [
            new Channel("orderbook.{$channelSymbol}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'orderbook.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'order_placed',
            'order' => [
                'id' => $this->order->id,
                'side' => $this->order->side,
                'price' => $this->order->price,
                'quantity' => $this->order->quantity,
                'status' => $this->order->status,
            ],
        ];
    }
}
