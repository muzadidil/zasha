<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in with correct credentials', function () {
    $user = User::factory()->customer()->create([
        'phone' => '+6281200001111',
        'password' => Hash::make('secret-pass-1'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'phone' => '+6281200001111',
        'password' => 'secret-pass-1',
        'device_name' => 'pest-test',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure(['data' => ['user', 'token']]);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
});

it('rejects login with wrong password', function () {
    User::factory()->customer()->create([
        'phone' => '+6281200002222',
        'password' => Hash::make('correct'),
    ]);

    $this->postJson('/api/auth/login', [
        'phone' => '+6281200002222',
        'password' => 'wrong-pass',
    ])
        ->assertStatus(401)
        ->assertJsonPath('error.domain', 'auth')
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('rejects login for unknown phone', function () {
    $this->postJson('/api/auth/login', [
        'phone' => '+6280000000000',
        'password' => 'whatever-pass',
    ])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('logs out and revokes current token', function () {
    $user = User::factory()->customer()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('data.logged_out', true);

    expect($user->tokens()->count())->toBe(0);
});

it('returns the authenticated user via /me', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('verifies phone with valid stub OTP', function () {
    $user = User::factory()->customer()->unverifiedPhone()->create();
    $expected = str_pad((string) (($user->id * 7919) % 1_000_000), 6, '0', STR_PAD_LEFT);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/auth/verify-phone', ['otp' => $expected])
        ->assertOk();

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

it('rejects invalid OTP', function () {
    $user = User::factory()->customer()->unverifiedPhone()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/auth/verify-phone', ['otp' => '000000'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'invalid_otp');
});
