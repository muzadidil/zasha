<?php

use App\Models\PartnerWallet;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\ServiceCategorySeeder;

beforeEach(function () {
    $this->seed(ServiceCategorySeeder::class);
});

it('creates a pending topup request from partner', function () {
    $partner = User::factory()->partner()->create();
    PartnerWallet::factory()->withBalance(20_000)->create(['user_id' => $partner->id]);

    $this->actingAs($partner, 'sanctum')
        ->postJson('/api/partner/wallet/topup-request', [
            'amount' => 30_000,
            'proof_url' => 'https://example.test/proof.png',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', TopupRequest::STATUS_PENDING);

    expect(TopupRequest::count())->toBe(1);
});

it('rejects topup request that would exceed Rp 100.000 cap', function () {
    $partner = User::factory()->partner()->create();
    PartnerWallet::factory()->withBalance(80_000)->create(['user_id' => $partner->id]);

    $this->actingAs($partner, 'sanctum')
        ->postJson('/api/partner/wallet/topup-request', [
            'amount' => 50_000,
            'proof_url' => 'https://example.test/proof.png',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'exceeds_max_balance');
});

it('approves a topup, credits the wallet, writes audit trail', function () {
    $partner = User::factory()->partner()->create();
    $wallet = PartnerWallet::factory()->withBalance(10_000)->create(['user_id' => $partner->id]);
    $topup = TopupRequest::create([
        'wallet_id' => $wallet->id,
        'amount' => 50_000,
        'proof_url' => 'https://example.test/proof.png',
        'status' => TopupRequest::STATUS_PENDING,
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/topup-requests/{$topup->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', TopupRequest::STATUS_APPROVED);

    expect($wallet->fresh()->balance)->toBe(60_000);
    expect(WalletTransaction::where('type', 'topup')->count())->toBe(1);
    expect(WalletTransaction::first()->balance_after)->toBe(60_000);
});

it('rejects approving an already processed topup', function () {
    $partner = User::factory()->partner()->create();
    $wallet = PartnerWallet::factory()->withBalance(0)->create(['user_id' => $partner->id]);
    $topup = TopupRequest::create([
        'wallet_id' => $wallet->id,
        'amount' => 50_000,
        'proof_url' => 'https://example.test/proof.png',
        'status' => TopupRequest::STATUS_APPROVED,
        'processed_at' => now(),
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/topup-requests/{$topup->id}/approve")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'topup_already_processed');
});

it('rejects a pending topup with reason', function () {
    $partner = User::factory()->partner()->create();
    $wallet = PartnerWallet::factory()->withBalance(0)->create(['user_id' => $partner->id]);
    $topup = TopupRequest::create([
        'wallet_id' => $wallet->id,
        'amount' => 50_000,
        'proof_url' => 'https://example.test/proof.png',
        'status' => TopupRequest::STATUS_PENDING,
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin, 'sanctum')
        ->postJson("/api/admin/topup-requests/{$topup->id}/reject", [
            'reason' => 'Bukti transfer tidak jelas.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', TopupRequest::STATUS_REJECTED);

    expect($wallet->fresh()->balance)->toBe(0);
});

it('shows partner wallet balance and max', function () {
    $partner = User::factory()->partner()->create();
    PartnerWallet::factory()->withBalance(25_000)->create(['user_id' => $partner->id]);

    $this->actingAs($partner, 'sanctum')
        ->getJson('/api/partner/wallet')
        ->assertOk()
        ->assertJsonPath('data.balance', 25_000)
        ->assertJsonPath('data.max_balance', 100_000);
});
