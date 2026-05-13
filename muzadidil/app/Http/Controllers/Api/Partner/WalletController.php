<?php

namespace App\Http\Controllers\Api\Partner;

use App\Exceptions\Wallet\WalletException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\TopupRequestRequest;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $wallet = $request->user()->partnerWallet;

        if (! $wallet) {
            throw WalletException::walletNotFound();
        }

        return response()->json([
            'data' => [
                'balance' => $wallet->balance,
                'max_balance' => \App\Models\PartnerWallet::MAX_BALANCE,
            ],
        ]);
    }

    public function topupRequest(TopupRequestRequest $request): JsonResponse
    {
        $topup = $this->walletService->createTopupRequest(
            partner: $request->user(),
            amount: (int) $request->input('amount'),
            proofUrl: (string) $request->input('proof_url'),
        );

        return response()->json([
            'data' => [
                'id' => $topup->id,
                'amount' => $topup->amount,
                'status' => $topup->status,
                'created_at' => $topup->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function transactions(Request $request): JsonResponse
    {
        $wallet = $request->user()->partnerWallet;

        if (! $wallet) {
            throw WalletException::walletNotFound();
        }

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
