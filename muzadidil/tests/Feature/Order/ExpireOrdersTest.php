<?php

use App\Models\Order;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

it('expires orders past their deadline via orders:expire command', function () {
    $customer = User::factory()->customer()->create();
    $titip = ServiceCategory::where('slug', 'titip')->first();

    $expiredOrder = Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $titip->id,
        'details' => [],
        'current_price' => 15_000,
        'initial_price' => 15_000,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->subMinutes(5),
    ]);

    $activeOrder = Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $titip->id,
        'details' => [],
        'current_price' => 15_000,
        'initial_price' => 15_000,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->artisan('orders:expire')->assertExitCode(0);

    expect($expiredOrder->fresh()->status)->toBe(Order::STATUS_EXPIRED);
    expect($activeOrder->fresh()->status)->toBe(Order::STATUS_SEARCHING);
    expect($expiredOrder->statusLogs()->where('to_status', 'expired')->count())->toBe(1);
});
