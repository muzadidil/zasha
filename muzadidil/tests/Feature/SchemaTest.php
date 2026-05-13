<?php

use App\Models\PartnerLocation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

it('has all 11 domain tables', function () {
    $expected = [
        'service_categories',
        'users',
        'partner_profiles',
        'partner_locations',
        'partner_wallets',
        'wallet_transactions',
        'topup_requests',
        'orders',
        'order_claims',
        'order_status_logs',
        'ratings',
    ];

    foreach ($expected as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Missing table: {$table}");
    }
});

it('can insert and decode spatial POINT coordinates', function () {
    $partner = User::factory()->partner()->create();

    $location = PartnerLocation::upsertCoordinates(
        userId: $partner->id,
        latitude: -6.2088,
        longitude: 106.8456,
        accuracy: 15,
    );

    $coords = $location->latLng();
    expect($coords)->not->toBeNull();
    expect($coords['lat'])->toEqualWithDelta(-6.2088, 0.0001);
    expect($coords['lng'])->toEqualWithDelta(106.8456, 0.0001);
});
