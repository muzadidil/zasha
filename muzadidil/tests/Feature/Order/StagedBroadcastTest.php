<?php

use App\Events\OrderAvailableForPartner;
use App\Events\OrderRadiusExpanded;
use App\Jobs\ExpandSearchRadiusJob;
use App\Models\Order;
use App\Models\OrderRadiusExpansion;
use App\Models\PartnerLocation;
use App\Models\PartnerProfile;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\Order\OrderBroadcastService;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

function makeVerifiedPartnerAt(float $lat, float $lng, array $categories = ['titip']): User
{
    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $partner->id,
        'service_categories' => $categories,
    ]);
    PartnerLocation::upsertCoordinates($partner->id, $lat, $lng);
    $partner->partnerLocation->is_online = true;
    $partner->partnerLocation->last_seen_at = now();
    $partner->partnerLocation->save();

    return $partner;
}

function makeStagedSearchingOrder(User $customer, string $slug = 'titip', int $price = 15_000): Order
{
    $category = ServiceCategory::where('slug', $slug)->firstOrFail();
    $order = new Order([
        'customer_id' => $customer->id,
        'service_category_id' => $category->id,
        'details' => ['stub' => true],
        'current_price' => $price,
        'initial_price' => $price,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinute(),
    ]);
    $order->save();

    DB::statement("UPDATE orders SET pickup_location = ST_GeomFromText('POINT(106.8 -6.2)') WHERE id = ?", [$order->id]);

    return $order->refresh();
}

it('expands radius step-by-step and only notifies partners newly in range', function () {
    Event::fake([OrderAvailableForPartner::class, OrderRadiusExpanded::class]);
    Queue::fake();

    $customer = User::factory()->customer()->create();
    $order = makeStagedSearchingOrder($customer);

    // Partner within 1km (~0.5km north).
    $near = makeVerifiedPartnerAt(-6.1955, 106.8);
    // Partner within 3km (~2.5km north of pickup).
    $mid = makeVerifiedPartnerAt(-6.1775, 106.8);

    $svc = app(OrderBroadcastService::class);
    $svc->expandToStep($order, 0); // radius 1km

    Event::assertDispatched(OrderAvailableForPartner::class, fn ($e) => $e->partner->id === $near->id);
    Event::assertNotDispatched(OrderAvailableForPartner::class, fn ($e) => $e->partner->id === $mid->id);

    expect(OrderRadiusExpansion::where('order_id', $order->id)->count())->toBe(1);
    expect($order->fresh()->active_radius_km)->toBe(1);
    expect($order->fresh()->current_step_index)->toBe(0);

    Queue::assertPushed(ExpandSearchRadiusJob::class, fn ($j) => $j->orderId === $order->id && $j->stepIndex === 1);

    // Expand to 3km — previous partner at 0.5km already covered, only mid should be newly notified.
    $svc->expandToStep($order->fresh(), 2);

    Event::assertDispatched(OrderAvailableForPartner::class, fn ($e) => $e->partner->id === $mid->id);
    $nearCount = collect(Event::dispatched(OrderAvailableForPartner::class))
        ->filter(fn ($args) => $args[0]->partner->id === $near->id)
        ->count();
    expect($nearCount)->toBe(1);
});

it('skips geo expansion for WFH orders and broadcasts nationally', function () {
    Event::fake([OrderAvailableForPartner::class, OrderRadiusExpanded::class]);
    Queue::fake();

    $customer = User::factory()->customer()->create();
    $wfh = ServiceCategory::where('slug', 'wfh')->firstOrFail();
    $order = new Order([
        'customer_id' => $customer->id,
        'service_category_id' => $wfh->id,
        'details' => ['stub' => true],
        'current_price' => 100_000,
        'initial_price' => 100_000,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinute(),
    ]);
    $order->save();

    // Two WFH-serving partners with no location data.
    $p1 = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $p1->id,
        'service_categories' => ['wfh'],
    ]);
    $p2 = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $p2->id,
        'service_categories' => ['wfh'],
    ]);

    app(OrderBroadcastService::class)->startStagedBroadcast($order->refresh());

    Event::assertDispatched(OrderAvailableForPartner::class, 2);
    Event::assertNotDispatched(OrderRadiusExpanded::class);
    Queue::assertNothingPushed();
});

it('does nothing when the queued expand job runs on a non-searching order', function () {
    Event::fake([OrderAvailableForPartner::class, OrderRadiusExpanded::class]);

    $customer = User::factory()->customer()->create();
    $order = makeStagedSearchingOrder($customer);

    // Simulate the order being claimed/cancelled before the job fires.
    $order->status = Order::STATUS_CANCELLED;
    $order->save();

    $job = new ExpandSearchRadiusJob($order->id, 0);
    $job->handle(app(OrderBroadcastService::class));

    Event::assertNotDispatched(OrderAvailableForPartner::class);
    Event::assertNotDispatched(OrderRadiusExpanded::class);
    expect(OrderRadiusExpansion::where('order_id', $order->id)->count())->toBe(0);
});

it('clears radius fields when an order is claimed', function () {
    $customer = User::factory()->customer()->create();
    $order = makeStagedSearchingOrder($customer);
    $order->active_radius_km = 2;
    $order->current_step_index = 1;
    $order->save();

    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $partner->id,
        'service_categories' => ['titip'],
    ]);
    \App\Models\PartnerWallet::factory()->withBalance(5_000)->create(['user_id' => $partner->id]);

    $this->actingAs($partner, 'sanctum')
        ->postJson("/api/partner/orders/{$order->id}/claim")
        ->assertOk();

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(Order::STATUS_CLAIMED);
    expect($fresh->active_radius_km)->toBeNull();
    expect($fresh->current_step_index)->toBeNull();
});

it('clears radius fields when an order is cancelled by customer', function () {
    Event::fake([\App\Events\OrderCancelled::class]);

    $customer = User::factory()->customer()->create();
    $order = makeStagedSearchingOrder($customer);
    $order->active_radius_km = 1;
    $order->current_step_index = 0;
    $order->save();

    $this->actingAs($customer, 'sanctum')
        ->deleteJson("/api/customer/orders/{$order->id}/cancel")
        ->assertOk();

    $fresh = $order->fresh();
    expect($fresh->status)->toBe(Order::STATUS_CANCELLED);
    expect($fresh->active_radius_km)->toBeNull();
    expect($fresh->current_step_index)->toBeNull();
    Event::assertDispatched(\App\Events\OrderCancelled::class);
});
