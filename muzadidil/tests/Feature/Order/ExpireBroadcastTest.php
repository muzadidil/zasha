<?php

use App\Events\OrderExpired;
use App\Models\Order;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

function makeExpiredSearchingOrder(User $customer, string $slug = 'titip'): Order
{
    $category = ServiceCategory::where('slug', $slug)->firstOrFail();
    $order = new Order([
        'customer_id' => $customer->id,
        'service_category_id' => $category->id,
        'details' => ['stub' => true],
        'current_price' => 15_000,
        'initial_price' => 15_000,
        'status' => Order::STATUS_SEARCHING,
        'active_radius_km' => 2,
        'current_step_index' => 1,
        'expires_at' => now()->subSeconds(5),
    ]);
    $order->save();

    DB::statement("UPDATE orders SET pickup_location = ST_GeomFromText('POINT(106.8 -6.2)') WHERE id = ?", [$order->id]);

    return $order->refresh();
}

it('removes order from partner feed when expired (broadcasts OrderExpired on order channel)', function () {
    Event::fake([OrderExpired::class]);

    $customer = User::factory()->customer()->create();
    $order = makeExpiredSearchingOrder($customer);

    Artisan::call('orders:expire');

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(Order::STATUS_EXPIRED);
    expect($fresh->active_radius_km)->toBeNull();
    expect($fresh->current_step_index)->toBeNull();

    Event::assertDispatched(OrderExpired::class, function ($e) use ($order) {
        $channels = $e->broadcastOn();
        expect($channels)->toHaveCount(1);
        expect($channels[0]->name)->toBe("order.{$order->id}");

        return $e->order->id === $order->id;
    });
});

it('does nothing when there is no expired searching order', function () {
    Event::fake([OrderExpired::class]);

    $customer = User::factory()->customer()->create();
    $category = ServiceCategory::where('slug', 'titip')->firstOrFail();
    Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $category->id,
        'details' => ['stub' => true],
        'current_price' => 15_000,
        'initial_price' => 15_000,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinute(),
    ]);

    Artisan::call('orders:expire');

    Event::assertNotDispatched(OrderExpired::class);
});
