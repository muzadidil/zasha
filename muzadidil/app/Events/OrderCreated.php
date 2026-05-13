<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order)
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // Geo-hashed partner channel: in the simplest form we broadcast on the
        // category-wide channel and the frontend filters by category serviced + radius.
        return [
            new Channel("partners.{$this->order->serviceCategory->slug}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $pickup = $this->order->pickupLatLng();

        return [
            'order_id' => $this->order->id,
            'service_category' => $this->order->serviceCategory->slug,
            'current_price' => $this->order->current_price,
            'pickup' => $pickup,
            'expires_at' => $this->order->expires_at?->toIso8601String(),
        ];
    }
}
