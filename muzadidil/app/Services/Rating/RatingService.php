<?php

namespace App\Services\Rating;

use App\Exceptions\Order\OrderException;
use App\Exceptions\Rating\RatingException;
use App\Models\Order;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RatingService
{
    public function rate(string $orderId, User $rater, int $stars, ?string $comment, string $expectedRole): Rating
    {
        return DB::transaction(function () use ($orderId, $rater, $stars, $comment, $expectedRole) {
            $order = Order::find($orderId);
            if (! $order) {
                throw OrderException::notFound();
            }

            if ($order->status !== Order::STATUS_COMPLETED) {
                throw RatingException::orderNotCompleted();
            }

            $rateeId = match (true) {
                $expectedRole === Rating::ROLE_CUSTOMER && $order->customer_id === $rater->id => $order->partner_id,
                $expectedRole === Rating::ROLE_PARTNER && $order->partner_id === $rater->id => $order->customer_id,
                default => null,
            };

            if (! $rateeId) {
                throw RatingException::notParticipant();
            }

            if (Rating::where('order_id', $order->id)->where('rater_id', $rater->id)->exists()) {
                throw RatingException::alreadyRated();
            }

            return Rating::create([
                'order_id' => $order->id,
                'rater_id' => $rater->id,
                'ratee_id' => $rateeId,
                'rater_role' => $expectedRole,
                'stars' => $stars,
                'comment' => $comment,
            ]);
        });
    }
}
