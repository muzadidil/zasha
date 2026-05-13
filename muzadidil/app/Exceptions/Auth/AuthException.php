<?php

namespace App\Exceptions\Auth;

use App\Exceptions\KamusReportableException;

class AuthException extends KamusReportableException
{
    protected string $domain = 'auth';

    public static function invalidCredentials(): self
    {
        return self::make(
            'invalid_credentials',
            'Nomor telepon atau password salah.',
            httpStatus: 401,
        );
    }

    public static function phoneAlreadyRegistered(string $phone): self
    {
        return self::make(
            'phone_already_registered',
            'Nomor telepon sudah terdaftar.',
            httpStatus: 422,
        )->withContext(['phone' => $phone]);
    }

    public static function phoneNotVerified(): self
    {
        return self::make(
            'phone_not_verified',
            'Nomor telepon belum diverifikasi.',
            httpStatus: 403,
        );
    }

    public static function invalidOtp(): self
    {
        return self::make(
            'invalid_otp',
            'Kode OTP tidak valid atau sudah kedaluwarsa.',
            httpStatus: 422,
        );
    }

    public static function unauthenticated(): self
    {
        return self::make(
            'unauthenticated',
            'Anda harus login terlebih dahulu.',
            httpStatus: 401,
        );
    }

    /**
     * @param  array<int, string>  $allowedRoles
     */
    public static function forbiddenRole(array $allowedRoles): self
    {
        return self::make(
            'forbidden_role',
            'Anda tidak memiliki akses ke fitur ini.',
            httpStatus: 403,
        )->withContext(['allowed_roles' => $allowedRoles]);
    }
}
