<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectTopupRequest;
use App\Models\TopupRequest;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TopupApprovalController extends Controller
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', TopupRequest::STATUS_PENDING);

        $topups = TopupRequest::query()
            ->with('wallet.user')
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'data' => $topups->items(),
            'meta' => [
                'current_page' => $topups->currentPage(),
                'last_page' => $topups->lastPage(),
                'total' => $topups->total(),
            ],
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $topup = $this->walletService->approveTopup($id, $request->user());

        return response()->json([
            'data' => $topup,
        ]);
    }

    public function reject(RejectTopupRequest $request, int $id): JsonResponse
    {
        $topup = $this->walletService->rejectTopup($id, $request->user(), (string) $request->input('reason'));

        return response()->json([
            'data' => $topup,
        ]);
    }
}
