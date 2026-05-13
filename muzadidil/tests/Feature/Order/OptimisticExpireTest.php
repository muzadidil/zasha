<?php

use App\Events\OrderClaimed;
use App\Events\OrderExpired;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\PartnerLocation;
use App\Models\PartnerProfile;
use App\Models\PartnerWallet;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

function makeJustExpiredOrder(User $customer, string $slug = 'titip'): Order
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
        'expires_at' => now()->subSeconds(1),
    ]);
    $order->save();

    DB::statement("UPDATE orders SET pickup_location = ST_GeomFromText('POINT(106.8 -6.2)') WHERE id = ?", [$order->id]);

    return $order->refresh();
}

it('backend has a 10s scheduler delay that justifies frontend optimistic expire', function () {
    // The customer countdown reaches 0 the moment expires_at is reached. The
    // `orders:expire` scheduler however only runs every 10 seconds (see
    // routes/console.php), so OrderExpired can land up to 10s after the
    // countdown hit 0. This test pins the contract: the command, once it does
    // run, marks the order expired — which is why the frontend showing the
    // expired modal at second 0 is safe (the eventual event is the same
    // outcome).
    Event::fake([OrderExpired::class]);

    $customer = User::factory()->customer()->create();
    $order = makeJustExpiredOrder($customer);

    Artisan::call('orders:expire');

    expect($order->fresh()->status)->toBe(Order::STATUS_EXPIRED);
    Event::assertDispatched(OrderExpired::class, fn ($e) => $e->order->id === $order->id);
});

it('lets a partner win the race up until the order is actually marked expired', function () {
    // The race window the frontend protects against: an order whose
    // expires_at is in the past but `orders:expire` has not yet flipped its
    // status. A claim attempt during this gap MUST still succeed atomically —
    // the OrderClaimed event the frontend then receives is what rolls back
    // the optimistic expired modal into the late-claim modal.
    Event::fake([OrderClaimed::class, OrderExpired::class]);

    $customer = User::factory()->customer()->create();
    $order = makeJustExpiredOrder($customer);

    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $partner->id,
        'service_categories' => ['titip'],
    ]);
    PartnerWallet::factory()->withBalance(5_000)->create(['user_id' => $partner->id]);
    PartnerLocation::upsertCoordinates($partner->id, -6.2, 106.8);

    $this->actingAs($partner, 'sanctum')
        ->postJson("/api/partner/orders/{$order->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_CLAIMED);

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(Order::STATUS_CLAIMED);
    expect($fresh->partner_id)->toBe($partner->id);

    Event::assertDispatched(OrderClaimed::class, fn ($e) => $e->order->id === $order->id);

    // After a successful claim, orders:expire must NOT flip status back.
    Artisan::call('orders:expire');
    expect($order->fresh()->status)->toBe(Order::STATUS_CLAIMED);
    Event::assertNotDispatched(OrderExpired::class);
});

it('rejects a claim attempt once orders:expire has marked the order expired', function () {
    $customer = User::factory()->customer()->create();
    $order = makeJustExpiredOrder($customer);

    Artisan::call('orders:expire');

    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $partner->id,
        'service_categories' => ['titip'],
    ]);
    PartnerWallet::factory()->withBalance(5_000)->create(['user_id' => $partner->id]);
    PartnerLocation::upsertCoordinates($partner->id, -6.2, 106.8);

    $response = $this->actingAs($partner, 'sanctum')
        ->postJson("/api/partner/orders/{$order->id}/claim")
        ->assertStatus(409);

    expect($response->json('error.code'))->toBe('not_in_searching_state');
    expect(OrderClaim::where('order_id', $order->id)->count())->toBe(0);
});
