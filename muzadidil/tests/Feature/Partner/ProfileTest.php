<?php

use App\Models\PartnerProfile;
use App\Models\User;
use Database\Seeders\ServiceCategorySeeder;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

it('creates a partner profile on first PUT', function () {
    $partner = User::factory()->partner()->create();

    $this->actingAs($partner, 'sanctum')
        ->putJson('/api/partner/profile', [
            'ktp_number' => '1234567890123456',
            'ktp_photo_url' => 'https://example.test/ktp.jpg',
            'service_categories' => ['titip', 'tenaga'],
            'skills' => ['angkat barang', 'menyetir'],
            'bank_name' => 'BCA',
            'bank_account' => '1234567890',
            'vehicle_info' => ['type' => 'motor', 'plate' => 'B 1234 ABC'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.ktp_number_masked', '************3456')
        ->assertJsonPath('data.bank_account_masked', '******7890')
        ->assertJsonPath('data.is_verified', false);

    $profile = PartnerProfile::where('user_id', $partner->id)->firstOrFail();
    expect($profile->ktp_number)->toBe('1234567890123456');
    expect($profile->bank_account)->toBe('1234567890');
});

it('updates an existing profile in place', function () {
    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $partner->id,
        'service_categories' => ['titip'],
    ]);

    $this->actingAs($partner, 'sanctum')
        ->putJson('/api/partner/profile', [
            'ktp_number' => str_pad((string) $partner->partnerProfile->ktp_number, 16, '0'),
            'ktp_photo_url' => 'https://example.test/ktp.jpg',
            'service_categories' => ['titip', 'service'],
        ])
        ->assertOk();
});

it('revokes verification when KTP number changes', function () {
    $partner = User::factory()->partner()->create();
    PartnerProfile::factory()->verified()->create([
        'user_id' => $partner->id,
    ]);

    $this->actingAs($partner, 'sanctum')
        ->putJson('/api/partner/profile', [
            'ktp_number' => '9999999999999999',
            'ktp_photo_url' => 'https://example.test/ktp.jpg',
            'service_categories' => ['titip'],
        ])
        ->assertOk();

    expect($partner->partnerProfile->fresh()->is_verified)->toBeFalse();
});

it('rejects invalid KTP and rekening', function () {
    $partner = User::factory()->partner()->create();

    $this->actingAs($partner, 'sanctum')
        ->putJson('/api/partner/profile', [
            'ktp_number' => '123',
            'ktp_photo_url' => 'https://example.test/ktp.jpg',
            'service_categories' => ['titip'],
            'bank_account' => 'abcd',
        ])
        ->assertStatus(422);
});

it('forbids non-partner from profile endpoint', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/partner/profile')
        ->assertStatus(403);
});
