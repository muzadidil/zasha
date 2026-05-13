<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderMonitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::query()
            ->with(['serviceCategory', 'customer:id,name,phone', 'partner:id,name,phone'])
            ->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->query('service_category_id')) {
            $query->where('service_category_id', $categoryId);
        }

        $orders = $query->paginate(20);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}
