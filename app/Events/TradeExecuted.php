<?php

namespace App\Events;

use App\Models\Trade;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TradeExecuted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Trade $trade
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $symbol = $this->trade->buyerOrder->symbol;
        // Replace slash with dash for channel name (Pusher doesn't support slash)
        $channelSymbol = str_replace('/', '-', $symbol);

        return [
            new Channel("trades.{$channelSymbol}"),
            new Channel("orderbook.{$channelSymbol}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'trade.executed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'trade' => [
                'price' => $this->trade->price,
                'quantity' => $this->trade->quantity,
                'total' => $this->trade->total_value,
                'side' => $this->trade->maker_side, // From the perspective of the taker
                'trade_time' => $this->trade->created_at->toIso8601String(),
            ],
        ];
    }
}
