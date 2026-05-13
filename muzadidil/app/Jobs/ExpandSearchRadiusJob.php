<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Order\OrderBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpandSearchRadiusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $orderId,
        public readonly int $stepIndex,
    ) {
    }

    public function handle(OrderBroadcastService $broadcastService): void
    {
        $order = Order::find($this->orderId);

        if (! $order || $order->status !== Order::STATUS_SEARCHING) {
            return;
        }

        $broadcastService->expandToStep($order, $this->stepIndex);
    }
}
