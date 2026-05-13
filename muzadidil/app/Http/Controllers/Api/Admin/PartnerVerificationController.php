<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\Partner\PartnerException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminPartnerResource;
use App\Http\Resources\PartnerProfileResource;
use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerVerificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $verified = $request->query('verified');

        $query = User::query()
            ->where('role', User::ROLE_PARTNER)
            ->with('partnerProfile')
            ->whereHas('partnerProfile');

        if ($verified !== null) {
            $isVerified = filter_var($verified, FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('partnerProfile', fn ($q) => $q->where('is_verified', $isVerified));
        }

        $partners = $query->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => AdminPartnerResource::collection($partners),
            'meta' => [
                'current_page' => $partners->currentPage(),
                'last_page' => $partners->lastPage(),
                'total' => $partners->total(),
            ],
        ]);
    }

    public function verify(Request $request, int $id): JsonResponse
    {
        $profile = PartnerProfile::where('user_id', $id)->first();

        if (! $profile) {
            throw PartnerException::profileMissing();
        }

        $profile->is_verified = true;
        $profile->verified_at = now();
        $profile->save();

        return response()->json([
            'data' => PartnerProfileResource::make($profile->fresh()),
        ]);
    }
}
