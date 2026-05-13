<?php

use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\PartnerLocation;
use App\Models\PartnerProfile;
use App\Models\PartnerWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\ServiceCategorySeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

function makeReadyPartner(int $balance = 5_000, array $categories = ['titip']): User
{
    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $partner->id,
        'service_categories' => $categories,
    ]);
    PartnerWallet::factory()->withBalance($balance)->create(['user_id' => $partner->id]);
    PartnerLocation::upsertCoordinates($partner->id, -6.2, 106.8);

    return $partner;
}

function makeSearchingOrder(User $customer, string $slug = 'titip', int $price = 15_000): Order
{
    $category = \App\Models\ServiceCategory::where('slug', $slug)->firstOrFail();
    $order = new Order([
        'customer_id' => $customer->id,
        'service_category_id' => $category->id,
        'details' => ['stub' => true],
        'current_price' => $price,
        'initial_price' => $price,
        'status' => Order::STATUS_SEARCHING,
        'expires_at' => now()->addMinutes(30),
    ]);
    $order->save();

    DB::statement("UPDATE orders SET pickup_location = ST_GeomFromText('POINT(106.8 -6.2)') WHERE id = ?", [$order->id]);

    return $order->refresh();
}

it('allows a verified partner with sufficient balance to claim an order', function () {
    $customer = User::factory()->customer()->create();
    $order = makeSearchingOrder($customer);
    $partner = makeReadyPartner(balance: 5_000);

    $this->actingAs($partner, 'sanctum')
        ->postJson("/api/partner/orders/{$order->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_CLAIMED)
        ->assertJsonPath('data.partner_id', $partner->id);

    $partner->refresh()->partnerWallet->refresh();

    expect($partner->partnerWallet->balance)->toBe(5_000 - 750); // 5% of 15_000 = 750
    expect(OrderClaim::count())->toBe(1);
    expect(WalletTransaction::where('type', 'fee')->count())->toBe(1);
});

it('rejects claim when wallet has insufficient balance', function () {
    $customer = User::factory()->customer()->create();
    $order = makeSearchingOrder($customer);
    $partner = makeReadyPartner(balance: 100);

    $this->actingAs($partner, 'sanctum')
        ->postJson("/api/partner/orders/{$order->id}/claim")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'insufficient_balance');
});

it('rejects claim from unverified partner', function () {
    $customer = User::factory()->customer()->create();
    $order = makeSearchingOrder($customer);

    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->create([
        'user_id' => $partner->id,
        'service_categories' => ['titip'],
        'is_verified' => false,
    ]);
    PartnerWallet::factory()->withBalance(5_000)->create(['user_id' => $partner->id]);

    $this->actingAs($partner, 'sanctum')
        ->postJson("/api/partner/orders/{$order->id}/claim")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'not_verified');
});

it('rejects claim for unsupported category', function () {
    $customer = User::factory()->customer()->create();
    $order = makeSearchingOrder($customer, slug: 'titip');
    $partner = makeReadyPartner(balance: 5_000, categories: ['tenaga']);

    $this->actingAs($partner, 'sanctum')
        ->postJson("/api/partner/orders/{$order->id}/claim")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'category_not_supported');
});

it('serialises concurrent claims so only one partner wins', function () {
    $customer = User::factory()->customer()->create();
    $order = makeSearchingOrder($customer);

    // Two qualified partners attempt to claim in sequence inside the same test.
    $first = makeReadyPartner(balance: 5_000);
    $second = makeReadyPartner(balance: 5_000);

    $r1 = $this->actingAs($first, 'sanctum')->postJson("/api/partner/orders/{$order->id}/claim");
    $r2 = $this->actingAs($second, 'sanctum')->postJson("/api/partner/orders/{$order->id}/claim");

    $r1->assertOk();
    $r2->assertStatus(409); // already claimed / not_in_searching_state

    expect(OrderClaim::where('status', 'success')->count())->toBe(1);
});

it('lets Tenaga collect up to max_partners (10) claims before flipping to claimed', function () {
    $customer = User::factory()->customer()->create();
    $order = makeSearchingOrder($customer, slug: 'tenaga', price: 100_000);

    // 10 qualified partners — each pays 5% of 100k = 5000.
    for ($i = 0; $i < 10; $i++) {
        $p = makeReadyPartner(balance: 6_000, categories: ['tenaga']);
        $response = $this->actingAs($p, 'sanctum')
            ->postJson("/api/partner/orders/{$order->id}/claim");
        $response->assertOk();
    }

    expect(OrderClaim::count())->toBe(10);
    expect($order->fresh()->status)->toBe(Order::STATUS_CLAIMED);

    // 11th attempt should fail.
    $eleventh = makeReadyPartner(balance: 6_000, categories: ['tenaga']);
    $this->actingAs($eleventh, 'sanctum')
        ->postJson("/api/partner/orders/{$order->id}/claim")
        ->assertStatus(409);
});

it('progresses claimed order through in_progress to completed', function () {
    $customer = User::factory()->customer()->create();
    $order = makeSearchingOrder($customer);
    $partner = makeReadyPartner(balance: 5_000);

    $this->actingAs($partner, 'sanctum')->postJson("/api/partner/orders/{$order->id}/claim");

    $this->actingAs($partner, 'sanctum')
        ->patchJson("/api/partner/orders/{$order->id}/start")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_IN_PROGRESS);

    $this->actingAs($partner, 'sanctum')
        ->patchJson("/api/partner/orders/{$order->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', Order::STATUS_COMPLETED);

    expect($order->fresh()->completed_at)->not->toBeNull();
    // searching → claimed → in_progress → completed = 3 transition logs
    // (the searching seed wasn't logged because we built the order directly).
    expect($order->fresh()->statusLogs()->count())->toBeGreaterThanOrEqual(3);
});
