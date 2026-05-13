<?php

use App\Models\PartnerWallet;
use App\Models\User;

it('registers a customer successfully', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Andi Pelanggan',
        'phone' => '+6281234567890',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'role' => User::ROLE_CUSTOMER,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.role', 'customer')
        ->assertJsonPath('data.phone', '+6281234567890');

    expect(User::where('phone', '+6281234567890')->exists())->toBeTrue();
    expect(PartnerWallet::query()->count())->toBe(0);
});

it('creates a partner wallet on partner registration', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Budi Mitra',
        'phone' => '+6289998887776',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'role' => User::ROLE_PARTNER,
    ])->assertCreated();

    $user = User::where('phone', '+6289998887776')->first();
    expect($user->partnerWallet)->not->toBeNull();
    expect($user->partnerWallet->balance)->toBe(0);
});

it('rejects registration with duplicate phone', function () {
    User::factory()->create(['phone' => '+6281111111111']);

    $this->postJson('/api/auth/register', [
        'name' => 'Charlie',
        'phone' => '+6281111111111',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'role' => User::ROLE_CUSTOMER,
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_input');
});

it('rejects weak passwords', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Dewi',
        'phone' => '+6282222222222',
        'password' => 'short',
        'password_confirmation' => 'short',
        'role' => User::ROLE_CUSTOMER,
    ])->assertStatus(422);
});

it('rejects invalid role', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Eko',
        'phone' => '+6283333333333',
        'password' => 'secret-pass-1',
        'password_confirmation' => 'secret-pass-1',
        'role' => 'admin',
    ])->assertStatus(422);
});
