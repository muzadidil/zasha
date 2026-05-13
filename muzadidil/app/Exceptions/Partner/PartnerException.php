<?php

namespace App\Exceptions\Partner;

use App\Exceptions\KamusReportableException;

class PartnerException extends KamusReportableException
{
    protected string $domain = 'partner';

    public static function notVerified(): self
    {
        return self::make(
            'not_verified',
            'Akun mitra belum diverifikasi admin.',
            httpStatus: 403,
        );
    }

    public static function notInRadius(float $distanceKm, float $radiusKm): self
    {
        return self::make(
            'not_in_radius',
            'Anda berada di luar radius order ini.',
            httpStatus: 403,
        )->withContext(['distance_km' => $distanceKm, 'radius_km' => $radiusKm]);
    }

    public static function notOnline(): self
    {
        return self::make(
            'not_online',
            'Aktifkan status online terlebih dahulu.',
            httpStatus: 409,
        );
    }

    public static function categoryNotSupported(string $slug): self
    {
        return self::make(
            'category_not_supported',
            'Anda tidak melayani kategori jasa ini.',
            httpStatus: 403,
        )->withContext(['category_slug' => $slug]);
    }

    public static function profileMissing(): self
    {
        return self::make(
            'profile_missing',
            'Profil mitra belum dilengkapi.',
            httpStatus: 422,
        );
    }

    public static function locationMissing(): self
    {
        return self::make(
            'location_missing',
            'Lokasi mitra belum dikirim.',
            httpStatus: 422,
        );
    }
}
