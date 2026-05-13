<?php

use App\Models\Order;
use App\Models\Rating;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

function makeCompletedOrder(User $customer, User $partner): Order
{
    $titip = ServiceCategory::where('slug', 'titip')->first();

    return Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $titip->id,
        'partner_id' => $partner->id,
        'details' => [],
        'current_price' => 15_000,
        'initial_price' => 15_000,
        'status' => Order::STATUS_COMPLETED,
        'expires_at' => now()->subMinutes(1),
        'claimed_at' => now()->subMinutes(20),
        'completed_at' => now()->subMinutes(1),
    ]);
}

it('allows customer to rate partner and refreshes partner average_rating', function () {
    $customer = User::factory()->customer()->create();
    $partner = User::factory()->partner()->create();
    $order = makeCompletedOrder($customer, $partner);

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/customer/orders/{$order->id}/rate", [
            'stars' => 5,
            'comment' => 'Mantap',
        ])
        ->assertCreated()
        ->assertJsonPath('data.stars', 5)
        ->assertJsonPath('data.rater_role', 'customer');

    expect(Rating::count())->toBe(1);
    expect($partner->fresh()->average_rating)->toEqual('5.00');
    expect($partner->fresh()->rating_count)->toBe(1);
});

it('allows partner to rate customer', function () {
    $customer = User::factory()->customer()->create();
    $partner = User::factory()->partner()->create();
    $order = makeCompletedOrder($customer, $partner);

    $this->actingAs($partner, 'sanctum')
        ->postJson("/api/partner/orders/{$order->id}/rate", [
            'stars' => 4,
        ])
        ->assertCreated()
        ->assertJsonPath('data.rater_role', 'partner');

    expect($customer->fresh()->average_rating)->toEqual('4.00');
});

it('forbids rating before order completion', function () {
    $customer = User::factory()->customer()->create();
    $partner = User::factory()->partner()->create();
    $order = makeCompletedOrder($customer, $partner);
    $order->status = Order::STATUS_IN_PROGRESS;
    $order->save();

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/customer/orders/{$order->id}/rate", ['stars' => 5])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'order_not_completed');
});

it('prevents double-rating from same rater', function () {
    $customer = User::factory()->customer()->create();
    $partner = User::factory()->partner()->create();
    $order = makeCompletedOrder($customer, $partner);

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/customer/orders/{$order->id}/rate", ['stars' => 5]);
    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/customer/orders/{$order->id}/rate", ['stars' => 4])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'already_rated');
});

it('forbids non-participants from rating', function () {
    $customer = User::factory()->customer()->create();
    $partner = User::factory()->partner()->create();
    $stranger = User::factory()->customer()->create();
    $order = makeCompletedOrder($customer, $partner);

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/customer/orders/{$order->id}/rate", ['stars' => 1])
        ->assertStatus(403);
});
