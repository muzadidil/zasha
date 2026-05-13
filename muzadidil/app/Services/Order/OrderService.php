<?php

namespace App\Services\Order;

use App\Events\OrderCreated;
use App\Events\OrderPriceUpdated;
use App\Events\OrderStatusChanged;
use App\Exceptions\Order\OrderException;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function create(User $customer, array $data): Order
    {
        $category = ServiceCategory::where('slug', $data['service_category_slug'])->firstOrFail();

        if ($data['initial_price'] < $category->min_price) {
            throw OrderException::priceBelowMinimum($data['initial_price'], $category->min_price);
        }

        if ($category->requires_geolocation && (
            ! isset($data['pickup_lat']) || ! isset($data['pickup_lng'])
        )) {
            throw OrderException::geolocationRequired();
        }

        return DB::transaction(function () use ($customer, $category, $data) {
            $order = new Order([
                'customer_id' => $customer->id,
                'service_category_id' => $category->id,
                'details' => $data['details'],
                'current_price' => $data['initial_price'],
                'initial_price' => $data['initial_price'],
                'status' => Order::STATUS_DRAFT,
                'expires_at' => now()->addMinutes($category->search_timeout_minutes),
            ]);
            $order->save();

            // Backfill spatial columns with raw geometry when present.
            if (isset($data['pickup_lat'], $data['pickup_lng'])) {
                $this->writePoint($order->id, 'pickup_location', (float) $data['pickup_lat'], (float) $data['pickup_lng']);
            }
            if (isset($data['destination_lat'], $data['destination_lng'])) {
                $this->writePoint($order->id, 'destination_location', (float) $data['destination_lat'], (float) $data['destination_lng']);
            }

            // Initial draft is auto-promoted to searching as part of submission.
            OrderStateMachine::transition($order, Order::STATUS_SEARCHING, changedBy: $customer->id);

            broadcast(new OrderCreated($order->refresh()))->toOthers();

            return $order->fresh();
        });
    }

    public function increasePrice(Order $order, User $customer): Order
    {
        if ($order->customer_id !== $customer->id) {
            throw OrderException::notOwner();
        }

        if ($order->status !== Order::STATUS_SEARCHING) {
            throw OrderException::notInSearchingState($order->status);
        }

        $step = $order->serviceCategory->price_step;
        $order->current_price = $order->current_price + $step;
        $order->save();

        broadcast(new OrderPriceUpdated($order))->toOthers();

        return $order;
    }

    public function cancel(Order $order, User $actor, ?string $reason = null): Order
    {
        if (! in_array($order->status, [Order::STATUS_SEARCHING, Order::STATUS_CLAIMED, Order::STATUS_IN_PROGRESS], true)) {
            throw OrderException::invalidStateTransition($order->status, Order::STATUS_CANCELLED);
        }

        if (
            $actor->id !== $order->customer_id
            && $actor->id !== $order->partner_id
            && ! $actor->isAdmin()
        ) {
            throw OrderException::notOwner();
        }

        return DB::transaction(function () use ($order, $actor, $reason) {
            OrderStateMachine::transition($order, Order::STATUS_CANCELLED, changedBy: $actor->id, reason: $reason);
            broadcast(new OrderStatusChanged($order))->toOthers();

            return $order->fresh();
        });
    }

    public function start(Order $order, User $partner): Order
    {
        if ($order->partner_id !== $partner->id) {
            throw OrderException::notOwner();
        }

        return DB::transaction(function () use ($order, $partner) {
            OrderStateMachine::transition($order, Order::STATUS_IN_PROGRESS, changedBy: $partner->id);
            broadcast(new OrderStatusChanged($order))->toOthers();

            return $order->fresh();
        });
    }

    public function complete(Order $order, User $partner): Order
    {
        if ($order->partner_id !== $partner->id) {
            throw OrderException::notOwner();
        }

        return DB::transaction(function () use ($order, $partner) {
            $order->completed_at = now();
            $order->save();
            OrderStateMachine::transition($order, Order::STATUS_COMPLETED, changedBy: $partner->id);
            broadcast(new OrderStatusChanged($order))->toOthers();

            return $order->fresh();
        });
    }

    private function writePoint(string $orderId, string $column, float $lat, float $lng): void
    {
        $point = sprintf('POINT(%F %F)', $lng, $lat);
        DB::statement(
            "UPDATE orders SET {$column} = ST_GeomFromText(?) WHERE id = ?",
            [$point, $orderId],
        );
    }
}
