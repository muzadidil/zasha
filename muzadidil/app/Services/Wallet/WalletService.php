<?php

namespace App\Services\Wallet;

use App\Events\WalletBalanceUpdated;
use App\Exceptions\Wallet\WalletException;
use App\Models\PartnerWallet;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public function createTopupRequest(User $partner, int $amount, string $proofUrl): TopupRequest
    {
        $wallet = $partner->partnerWallet;

        if (! $wallet) {
            throw WalletException::walletNotFound();
        }

        if ($wallet->balance + $amount > PartnerWallet::MAX_BALANCE) {
            throw WalletException::exceedsMaxBalance($wallet->balance + $amount);
        }

        return TopupRequest::create([
            'wallet_id' => $wallet->id,
            'amount' => $amount,
            'proof_url' => $proofUrl,
            'status' => TopupRequest::STATUS_PENDING,
        ]);
    }

    public function approveTopup(int $topupId, User $admin): TopupRequest
    {
        return DB::transaction(function () use ($topupId, $admin) {
            $topup = TopupRequest::query()->whereKey($topupId)->lockForUpdate()->first();

            if (! $topup) {
                throw WalletException::topupNotFound();
            }

            if (! $topup->isPending()) {
                throw WalletException::topupAlreadyProcessed($topup->status);
            }

            $wallet = PartnerWallet::query()->whereKey($topup->wallet_id)->lockForUpdate()->firstOrFail();

            $newBalance = $wallet->balance + $topup->amount;
            if ($newBalance > PartnerWallet::MAX_BALANCE) {
                throw WalletException::exceedsMaxBalance($newBalance);
            }

            $wallet->balance = $newBalance;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => WalletTransaction::TYPE_TOPUP,
                'amount' => $topup->amount,
                'balance_after' => $newBalance,
                'reference_type' => TopupRequest::class,
                'reference_id' => (string) $topup->id,
                'notes' => 'Top-up disetujui admin',
            ]);

            $topup->status = TopupRequest::STATUS_APPROVED;
            $topup->processed_by = $admin->id;
            $topup->processed_at = now();
            $topup->save();

            broadcast(new WalletBalanceUpdated($wallet))->toOthers();

            return $topup->fresh();
        });
    }

    public function rejectTopup(int $topupId, User $admin, string $reason): TopupRequest
    {
        return DB::transaction(function () use ($topupId, $admin, $reason) {
            $topup = TopupRequest::query()->whereKey($topupId)->lockForUpdate()->first();

            if (! $topup) {
                throw WalletException::topupNotFound();
            }

            if (! $topup->isPending()) {
                throw WalletException::topupAlreadyProcessed($topup->status);
            }

            $topup->status = TopupRequest::STATUS_REJECTED;
            $topup->processed_by = $admin->id;
            $topup->processed_at = now();
            $topup->rejection_reason = $reason;
            $topup->save();

            return $topup->fresh();
        });
    }
}
