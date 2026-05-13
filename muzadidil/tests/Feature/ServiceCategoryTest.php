<?php

use Database\Seeders\ServiceCategorySeeder;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

it('lists the 4 seeded service categories', function () {
    $response = $this->getJson('/api/service-categories');

    $response->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonFragment(['slug' => 'wfh'])
        ->assertJsonFragment(['slug' => 'titip'])
        ->assertJsonFragment(['slug' => 'tenaga'])
        ->assertJsonFragment(['slug' => 'service']);
});

it('returns category config matching spec', function () {
    $response = $this->getJson('/api/service-categories');

    $titip = collect($response->json('data'))->firstWhere('slug', 'titip');

    expect($titip['min_price'])->toBe(10_000);
    expect($titip['price_step'])->toBe(5_000);
    expect($titip['max_partners'])->toBe(1);
    expect($titip['requires_geolocation'])->toBeTrue();
    expect($titip['search_radius_km'])->toBe(4);
    expect($titip['search_timeout_minutes'])->toBe(1);
});

it('returns null radius for WFH category', function () {
    $response = $this->getJson('/api/service-categories');

    $wfh = collect($response->json('data'))->firstWhere('slug', 'wfh');

    expect($wfh['search_radius_km'])->toBeNull();
    expect($wfh['requires_geolocation'])->toBeFalse();
    expect($wfh['search_timeout_minutes'])->toBe(1);
});
