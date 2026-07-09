<?php

namespace App\Events;

use App\Models\Kline;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KlineUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Kline $kline
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // Replace slash with dash for channel name
        $channelSymbol = str_replace('/', '-', $this->kline->symbol);

        return [
            new Channel("kline.{$channelSymbol}.{$this->kline->interval}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'kline.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'e' => 'kline',     // Event type
            's' => $this->kline->symbol,
            'k' => [
                't' => $this->kline->open_time * 1000,  // Kline start time (ms)
                'T' => $this->kline->close_time * 1000, // Kline close time (ms)
                's' => $this->kline->symbol,
                'i' => $this->kline->interval,
                'o' => $this->kline->open,
                'h' => $this->kline->high,
                'l' => $this->kline->low,
                'c' => $this->kline->close,
                'v' => $this->kline->volume,
                'q' => $this->kline->quote_volume,
                'n' => $this->kline->trades_count,
                'x' => $this->kline->is_closed,  // Is this kline closed?
            ],
        ];
    }
}
