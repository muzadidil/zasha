<?php

namespace App\Services\Order;

use App\Events\OrderClaimed;
use App\Events\WalletBalanceUpdated;
use App\Exceptions\Order\OrderException;
use App\Exceptions\Partner\PartnerException;
use App\Exceptions\Wallet\WalletException;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\PartnerWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class OrderClaimService
{
    public function claim(string $orderId, User $partner): Order
    {
        if (! $partner->isPartner()) {
            throw PartnerException::profileMissing();
        }

        $profile = $partner->partnerProfile;
        if (! $profile || ! $profile->is_verified) {
            throw PartnerException::notVerified();
        }

        return DB::transaction(function () use ($orderId, $partner, $profile) {
            // Lock the order row first; this serialises every concurrent claim attempt.
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                throw OrderException::notFound();
            }

            if ($order->status !== Order::STATUS_SEARCHING) {
                throw OrderException::notInSearchingState($order->status);
            }

            $category = $order->serviceCategory;

            if (! $profile->servesCategory($category->slug)) {
                throw PartnerException::categoryNotSupported($category->slug);
            }

            $successfulClaims = OrderClaim::where('order_id', $orderId)
                ->where('status', OrderClaim::STATUS_SUCCESS)
                ->count();

            if ($successfulClaims >= $category->max_partners) {
                throw OrderException::maxClaimsReached();
            }

            $fee = $category->feeFor($order->current_price);

            $wallet = PartnerWallet::query()
                ->where('user_id', $partner->id)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw WalletException::walletNotFound();
            }

            if ($wallet->balance < $fee) {
                throw WalletException::insufficientBalance($fee, $wallet->balance);
            }

            $newBalance = $wallet->balance - $fee;
            $wallet->balance = $newBalance;
            $wallet->save();

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => WalletTransaction::TYPE_FEE,
                'amount' => -$fee,
                'balance_after' => $newBalance,
                'reference_type' => Order::class,
                'reference_id' => $order->id,
                'notes' => "Fee 5% untuk order {$order->id}",
            ]);

            OrderClaim::create([
                'order_id' => $order->id,
                'partner_id' => $partner->id,
                'agreed_price' => $order->current_price,
                'fee_amount' => $fee,
                'status' => OrderClaim::STATUS_SUCCESS,
                'claimed_at' => now(),
            ]);

            // For single-partner categories the order moves to claimed; for Tenaga
            // it stays searching until max_partners is reached.
            $becomesClaimed = $category->max_partners === 1 || ($successfulClaims + 1) >= $category->max_partners;

            if ($becomesClaimed) {
                $order->partner_id = $partner->id;
                $order->claimed_at = now();
                $order->active_radius_km = null;
                $order->current_step_index = null;
                $order->save();
                OrderStateMachine::transition($order, Order::STATUS_CLAIMED, changedBy: $partner->id);
            }

            broadcast(new OrderClaimed($order->fresh(), $partner))->toOthers();
            broadcast(new WalletBalanceUpdated($wallet))->toOthers();

            return $order->fresh();
        });
    }
}
