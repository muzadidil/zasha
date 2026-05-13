<?php

namespace App\Services\Order;

use App\Exceptions\Order\OrderException;
use App\Models\Order;
use App\Models\OrderStatusLog;

class OrderStateMachine
{
    /**
     * Allowed transitions per spec section 5.
     *
     * @var array<string, array<int, string>>
     */
    private const TRANSITIONS = [
        Order::STATUS_DRAFT => [Order::STATUS_SEARCHING],
        Order::STATUS_SEARCHING => [Order::STATUS_CLAIMED, Order::STATUS_EXPIRED, Order::STATUS_CANCELLED],
        Order::STATUS_CLAIMED => [Order::STATUS_IN_PROGRESS, Order::STATUS_CANCELLED],
        Order::STATUS_IN_PROGRESS => [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED],
    ];

    public static function transition(
        Order $order,
        string $to,
        ?int $changedBy = null,
        ?string $reason = null,
    ): Order {
        $from = $order->status;

        if (! self::canTransition($from, $to)) {
            throw OrderException::invalidStateTransition($from, $to);
        }

        $order->status = $to;
        $order->save();

        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $changedBy,
            'reason' => $reason,
        ]);

        return $order;
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
