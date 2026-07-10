<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcast
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
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->order->user_id}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.status.changed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'symbol' => $this->order->symbol,
                'side' => $this->order->side,
                'type' => $this->order->type ?? 'limit',
                'price' => $this->order->price,
                'quantity' => $this->order->quantity,
                'filled_quantity' => $this->order->filled_quantity,
                'remaining_quantity' => $this->order->remaining_quantity,
                'total' => $this->order->total_value,
                'status' => $this->order->status,
                'updated_at' => $this->order->updated_at->toIso8601String(),
            ],
        ];
    }
}
