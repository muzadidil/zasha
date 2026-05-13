<?php

use App\Models\Order;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('order.{id}', function ($user, $id) {
    $order = Order::find($id);
    if (! $order) {
        return false;
    }

    return $order->customer_id === $user->id || $order->partner_id === $user->id;
});
