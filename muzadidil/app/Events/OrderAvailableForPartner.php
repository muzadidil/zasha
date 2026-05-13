<?php

namespace App\Events;

use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderAvailableForPartner implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly User $partner,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("partner.{$this->partner->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'OrderAvailableForPartner';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'category' => $this->order->serviceCategory->slug,
            'current_price' => $this->order->current_price,
            'details' => $this->order->details,
            'pickup' => $this->order->pickupLatLng(),
            'distance_km' => $this->order->active_radius_km,
            'expires_at' => $this->order->expires_at?->toIso8601String(),
        ];
    }
}
