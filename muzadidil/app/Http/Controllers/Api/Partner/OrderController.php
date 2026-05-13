<?php

namespace App\Http\Controllers\Api\Partner;

use App\Exceptions\Order\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Order\AvailableOrderService;
use App\Services\Order\OrderClaimService;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly AvailableOrderService $availableOrderService,
        private readonly OrderClaimService $orderClaimService,
        private readonly OrderService $orderService,
    ) {
    }

    public function available(Request $request): JsonResponse
    {
        $orders = $this->availableOrderService->listFor($request->user());

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with('serviceCategory')
            ->where('partner_id', $request->user()->id)
            ->orderByDesc('claimed_at')
            ->paginate(20);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = $this->findPartnerOrderOrFail($id, $request);

        return response()->json([
            'data' => OrderResource::make($order->load('serviceCategory')),
        ]);
    }

    public function claim(Request $request, string $id): JsonResponse
    {
        $order = $this->orderClaimService->claim($id, $request->user());

        return response()->json([
            'data' => OrderResource::make($order->load('serviceCategory')),
        ]);
    }

    public function start(Request $request, string $id): JsonResponse
    {
        $order = $this->findPartnerOrderOrFail($id, $request);
        $started = $this->orderService->start($order, $request->user());

        return response()->json([
            'data' => OrderResource::make($started->load('serviceCategory')),
        ]);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $order = $this->findPartnerOrderOrFail($id, $request);
        $completed = $this->orderService->complete($order, $request->user());

        return response()->json([
            'data' => OrderResource::make($completed->load('serviceCategory')),
        ]);
    }

    private function findPartnerOrderOrFail(string $id, Request $request): Order
    {
        $order = Order::find($id);

        if (! $order) {
            throw OrderException::notFound();
        }

        if ($order->partner_id !== $request->user()->id) {
            throw OrderException::notOwner();
        }

        return $order;
    }
}
