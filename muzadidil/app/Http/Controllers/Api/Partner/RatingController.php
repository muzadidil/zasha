<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Common\RateOrderRequest;
use App\Models\Rating;
use App\Services\Rating\RatingService;
use Illuminate\Http\JsonResponse;

class RatingController extends Controller
{
    public function __construct(private readonly RatingService $ratingService)
    {
    }

    public function store(RateOrderRequest $request, string $id): JsonResponse
    {
        $rating = $this->ratingService->rate(
            orderId: $id,
            rater: $request->user(),
            stars: (int) $request->input('stars'),
            comment: $request->input('comment'),
            expectedRole: Rating::ROLE_PARTNER,
        );

        return response()->json([
            'data' => $rating,
        ], 201);
    }
}
