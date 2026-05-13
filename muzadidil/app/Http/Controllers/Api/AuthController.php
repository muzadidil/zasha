<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyPhoneRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json([
            'data' => UserResource::make($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->attempt(
            phone: $request->string('phone'),
            password: $request->string('password'),
            deviceName: $request->input('device_name'),
        );

        return response()->json([
            'data' => [
                'user' => UserResource::make($result['user']),
                'token' => $result['token']->plainTextToken,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['data' => ['logged_out' => true]]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => UserResource::make($request->user()),
        ]);
    }

    public function verifyPhone(VerifyPhoneRequest $request): JsonResponse
    {
        $user = $this->authService->markPhoneVerified(
            user: $request->user(),
            otp: $request->string('otp'),
        );

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }
}
