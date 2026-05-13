<?php

use App\Models\Order;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

it('lists the partners assigned orders', function () {
    $partner = User::factory()->partner()->create();
    $customer = User::factory()->customer()->create();
    $titip = ServiceCategory::where('slug', 'titip')->first();

    Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $titip->id,
        'partner_id' => $partner->id,
        'details' => [],
        'current_price' => 15_000,
        'initial_price' => 15_000,
        'status' => Order::STATUS_COMPLETED,
        'claimed_at' => now()->subHours(1),
        'completed_at' => now()->subMinutes(10),
    ]);

    // Unassigned order should not appear in this partners history.
    Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $titip->id,
        'details' => [],
        'current_price' => 20_000,
        'initial_price' => 20_000,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($partner, 'sanctum')
        ->getJson('/api/partner/orders/history')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', Order::STATUS_COMPLETED);
});

it('lets the assigned partner view a single order', function () {
    $partner = User::factory()->partner()->create();
    $other = User::factory()->partner()->create();
    $customer = User::factory()->customer()->create();
    $titip = ServiceCategory::where('slug', 'titip')->first();

    $order = Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $titip->id,
        'partner_id' => $partner->id,
        'details' => [],
        'current_price' => 15_000,
        'initial_price' => 15_000,
        'status' => Order::STATUS_IN_PROGRESS,
        'claimed_at' => now(),
    ]);

    $this->actingAs($partner, 'sanctum')
        ->getJson("/api/partner/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $order->id);

    $this->actingAs($other, 'sanctum')
        ->getJson("/api/partner/orders/{$order->id}")
        ->assertStatus(403);
});
