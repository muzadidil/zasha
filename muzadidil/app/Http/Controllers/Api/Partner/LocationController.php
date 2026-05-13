<?php

namespace App\Http\Controllers\Api\Partner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\UpdateLocationRequest;
use App\Http\Requests\Partner\UpdateOnlineStatusRequest;
use App\Services\Partner\PartnerLocationService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function __construct(private readonly PartnerLocationService $locationService)
    {
    }

    public function update(UpdateLocationRequest $request): JsonResponse
    {
        $location = $this->locationService->updateCoordinates(
            partner: $request->user(),
            lat: (float) $request->input('lat'),
            lng: (float) $request->input('lng'),
            accuracy: $request->input('accuracy_meters'),
        );

        return response()->json([
            'data' => [
                'is_online' => $location->is_online,
                'last_seen_at' => $location->last_seen_at?->toIso8601String(),
                'coordinates' => $location->latLng(),
            ],
        ]);
    }

    public function setOnline(UpdateOnlineStatusRequest $request): JsonResponse
    {
        $location = $this->locationService->setOnline(
            partner: $request->user(),
            online: (bool) $request->boolean('is_online'),
        );

        return response()->json([
            'data' => [
                'is_online' => $location->is_online,
                'last_seen_at' => $location->last_seen_at?->toIso8601String(),
            ],
        ]);
    }
}
