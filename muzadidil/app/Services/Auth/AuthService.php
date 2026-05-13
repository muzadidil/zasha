<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\AuthException;
use App\Models\PartnerWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

class AuthService
{
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'role' => $data['role'],
            ]);

            // Partner accounts get a zero-balance wallet immediately so the wallet
            // row exists by the time admin verifies the profile.
            if ($user->isPartner()) {
                PartnerWallet::create([
                    'user_id' => $user->id,
                    'balance' => 0,
                ]);
            }

            return $user->fresh();
        });
    }

    public function attempt(string $phone, string $password, ?string $deviceName = null): array
    {
        $user = User::where('phone', $phone)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw AuthException::invalidCredentials();
        }

        $token = $user->createToken(
            name: $deviceName ?? 'default',
            expiresAt: now()->addDays(7),
        );

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $current = $user->currentAccessToken();

        if ($current instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $current->delete();
        }
    }

    public function markPhoneVerified(User $user, string $otp): User
    {
        // Phase 1: stub verification. The actual OTP provider integration is Phase 6 work.
        // We accept any 6-digit OTP whose value matches the deterministic stub below so the
        // flow is testable end-to-end without an SMS provider.
        if ($otp !== $this->expectedOtpFor($user)) {
            throw AuthException::invalidOtp();
        }

        $user->forceFill(['phone_verified_at' => now()])->save();

        return $user->refresh();
    }

    public function expectedOtpFor(User $user): string
    {
        // Deterministic stub OTP derived from user id; replaced by real provider in Phase 6.
        return str_pad((string) (($user->id * 7919) % 1_000_000), 6, '0', STR_PAD_LEFT);
    }
}
