<?php

use App\Models\PartnerLocation;
use App\Models\User;

it('updates partner GPS coordinates', function () {
    $partner = User::factory()->partner()->create();

    $this->actingAs($partner, 'sanctum')
        ->patchJson('/api/partner/location', [
            'lat' => -6.2088,
            'lng' => 106.8456,
            'accuracy_meters' => 12,
        ])
        ->assertOk();

    $location = PartnerLocation::where('user_id', $partner->id)->firstOrFail();
    expect($location->latLng())->toMatchArray([
        'lat' => -6.2088,
        'lng' => 106.8456,
    ]);
});

it('rejects invalid latitude', function () {
    $partner = User::factory()->partner()->create();

    $this->actingAs($partner, 'sanctum')
        ->patchJson('/api/partner/location', [
            'lat' => 150,
            'lng' => 106.8,
        ])
        ->assertStatus(422);
});

it('toggles online status', function () {
    $partner = User::factory()->partner()->create();
    PartnerLocation::upsertCoordinates($partner->id, -6.2, 106.8);

    $this->actingAs($partner, 'sanctum')
        ->patchJson('/api/partner/online-status', ['is_online' => true])
        ->assertOk()
        ->assertJsonPath('data.is_online', true);
});

it('refuses online toggle if no location yet', function () {
    $partner = User::factory()->partner()->create();

    $this->actingAs($partner, 'sanctum')
        ->patchJson('/api/partner/online-status', ['is_online' => true])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'location_missing');
});

it('forbids non-partner from location endpoint', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer, 'sanctum')
        ->patchJson('/api/partner/location', ['lat' => -6.2, 'lng' => 106.8])
        ->assertStatus(403);
});
