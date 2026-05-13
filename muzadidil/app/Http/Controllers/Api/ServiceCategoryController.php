<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;

class ServiceCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => ServiceCategoryResource::collection($categories),
        ]);
    }
}
