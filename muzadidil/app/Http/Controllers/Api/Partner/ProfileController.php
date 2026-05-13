<?php

namespace App\Http\Controllers\Api\Partner;

use App\Exceptions\Partner\PartnerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\UpsertProfileRequest;
use App\Http\Resources\PartnerProfileResource;
use App\Services\Partner\PartnerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly PartnerProfileService $profileService)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->partnerProfile;

        if (! $profile) {
            throw PartnerException::profileMissing();
        }

        return response()->json([
            'data' => PartnerProfileResource::make($profile),
        ]);
    }

    public function upsert(UpsertProfileRequest $request): JsonResponse
    {
        $existed = $request->user()->partnerProfile()->exists();
        $profile = $this->profileService->upsert($request->user(), $request->validated());

        return response()->json([
            'data' => PartnerProfileResource::make($profile),
        ], $existed ? 200 : 201);
    }
}
