<?php

namespace App\Console\Commands;

use App\Events\OrderExpired;
use App\Models\Order;
use App\Models\OrderStatusLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireSearchingOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Mark searching orders past expires_at as expired';

    public function handle(): int
    {
        $expired = 0;

        Order::query()
            ->where('status', Order::STATUS_SEARCHING)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$expired) {
                foreach ($orders as $order) {
                    DB::transaction(function () use ($order) {
                        $order->refresh();
                        if ($order->status !== Order::STATUS_SEARCHING) {
                            return;
                        }
                        $previous = $order->status;
                        $order->status = Order::STATUS_EXPIRED;
                        $order->active_radius_km = null;
                        $order->current_step_index = null;
                        $order->save();

                        OrderStatusLog::create([
                            'order_id' => $order->id,
                            'from_status' => $previous,
                            'to_status' => Order::STATUS_EXPIRED,
                            'changed_by' => null,
                            'reason' => 'timeout',
                        ]);

                        broadcast(new OrderExpired($order->fresh()));
                    });
                    $expired++;
                }
            });

        $this->info("Expired {$expired} order(s).");

        return self::SUCCESS;
    }
}
