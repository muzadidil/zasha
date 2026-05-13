<?php

use App\Models\Order;
use App\Models\PartnerLocation;
use App\Models\PartnerProfile;
use App\Models\PartnerWallet;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

it('lists only orders in served categories within radius', function () {
    $customer = User::factory()->customer()->create();
    $titip = ServiceCategory::where('slug', 'titip')->first();
    $tenaga = ServiceCategory::where('slug', 'tenaga')->first();

    // Order at (-6.20, 106.80) — partner is at (-6.21, 106.80) ~1km away.
    $orderNear = Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $titip->id,
        'details' => [],
        'current_price' => 15_000,
        'initial_price' => 15_000,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinutes(10),
    ]);
    DB::statement("UPDATE orders SET pickup_location = ST_GeomFromText('POINT(106.80 -6.20)') WHERE id = ?", [$orderNear->id]);

    // Order 50km away — outside titip 5km radius.
    $orderFar = Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $titip->id,
        'details' => [],
        'current_price' => 15_000,
        'initial_price' => 15_000,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinutes(10),
    ]);
    DB::statement("UPDATE orders SET pickup_location = ST_GeomFromText('POINT(107.5 -6.5)') WHERE id = ?", [$orderFar->id]);

    // Order in an unserved category (tenaga) right at partner location — should be filtered out.
    $orderWrongCategory = Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $tenaga->id,
        'details' => [],
        'current_price' => 100_000,
        'initial_price' => 100_000,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinutes(10),
    ]);
    DB::statement("UPDATE orders SET pickup_location = ST_GeomFromText('POINT(106.80 -6.21)') WHERE id = ?", [$orderWrongCategory->id]);

    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $partner->id,
        'service_categories' => ['titip'],
    ]);
    PartnerWallet::factory()->withBalance(5_000)->create(['user_id' => $partner->id]);
    PartnerLocation::upsertCoordinates($partner->id, -6.21, 106.80);

    $response = $this->actingAs($partner, 'sanctum')
        ->getJson('/api/partner/orders/available')
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toContain($orderNear->id);
    expect($ids)->not->toContain($orderFar->id);
    expect($ids)->not->toContain($orderWrongCategory->id);
});

it('includes customer info (name + rating) on available orders so the feed can render it', function () {
    $customer = User::factory()->customer()->create(['name' => 'Budi Pelanggan', 'average_rating' => 4.7, 'rating_count' => 12]);
    $titip = ServiceCategory::where('slug', 'titip')->first();

    $order = Order::create([
        'customer_id' => $customer->id,
        'service_category_id' => $titip->id,
        'details' => ['pickup_address' => 'Jl. Jawa', 'dropoff_address' => 'Jl. Mastrip'],
        'current_price' => 20_000,
        'initial_price' => 20_000,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinute(),
    ]);
    DB::statement("UPDATE orders SET pickup_location = ST_GeomFromText('POINT(106.80 -6.20)') WHERE id = ?", [$order->id]);

    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create(['user_id' => $partner->id, 'service_categories' => ['titip']]);
    PartnerWallet::factory()->withBalance(5_000)->create(['user_id' => $partner->id]);
    PartnerLocation::upsertCoordinates($partner->id, -6.21, 106.80);

    $response = $this->actingAs($partner, 'sanctum')
        ->getJson('/api/partner/orders/available')
        ->assertOk();

    $entry = collect($response->json('data'))->firstWhere('id', $order->id);
    expect($entry)->not->toBeNull();
    expect($entry['customer']['name'])->toBe('Budi Pelanggan');
    expect($entry['customer']['average_rating'])->toBe(4.7);
    expect($entry['customer']['rating_count'])->toBe(12);
});
