<?php

namespace App\Http\Controllers\Api\Customer;

use App\Exceptions\Order\OrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CreateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with('serviceCategory')
            ->where('customer_id', $request->user()->id)
            ->orderByDesc('created_at')
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
        $order = $this->findOwnedOrFail($request, $id);

        return response()->json([
            'data' => OrderResource::make($order->load('serviceCategory')),
        ]);
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create($request->user(), $request->validated());

        return response()->json([
            'data' => OrderResource::make($order->load('serviceCategory')),
        ], 201);
    }

    public function increasePrice(Request $request, string $id): JsonResponse
    {
        $order = $this->findOwnedOrFail($request, $id);
        $updated = $this->orderService->increasePrice($order, $request->user());

        return response()->json([
            'data' => OrderResource::make($updated->load('serviceCategory')),
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = $this->findOwnedOrFail($request, $id);
        $cancelled = $this->orderService->cancel($order, $request->user(), reason: 'cancelled_by_customer');

        return response()->json([
            'data' => OrderResource::make($cancelled->load('serviceCategory')),
        ]);
    }

    private function findOwnedOrFail(Request $request, string $id): Order
    {
        $order = Order::find($id);

        if (! $order) {
            throw OrderException::notFound();
        }

        if ($order->customer_id !== $request->user()->id) {
            throw OrderException::notOwner();
        }

        return $order;
    }
}
