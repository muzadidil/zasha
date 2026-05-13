<?php

namespace App\Exceptions\Order;

use App\Exceptions\KamusReportableException;

class OrderException extends KamusReportableException
{
    protected string $domain = 'order';

    public static function invalidCategory(string $slug): self
    {
        return self::make(
            'invalid_category',
            'Kategori jasa tidak ditemukan.',
            httpStatus: 422,
        )->withContext(['slug' => $slug]);
    }

    public static function priceBelowMinimum(int $given, int $minimum): self
    {
        return self::make(
            'price_below_minimum',
            "Harga di bawah minimum (Rp {$minimum}).",
            httpStatus: 422,
        )
            ->withContext(['given' => $given, 'minimum' => $minimum])
            ->withPublicData(['minimum' => $minimum]);
    }

    public static function geolocationRequired(): self
    {
        return self::make(
            'geolocation_required',
            'Lokasi pickup wajib diisi untuk kategori ini.',
            httpStatus: 422,
        );
    }

    public static function alreadyClaimed(): self
    {
        return self::make(
            'already_claimed',
            'Order sudah diambil mitra lain.',
            httpStatus: 409,
        );
    }

    public static function notInSearchingState(string $currentStatus): self
    {
        return self::make(
            'not_in_searching_state',
            'Order tidak lagi tersedia untuk diambil.',
            httpStatus: 409,
        )->withContext(['current_status' => $currentStatus]);
    }

    public static function invalidStateTransition(string $from, string $to): self
    {
        return self::make(
            'invalid_state_transition',
            'Transisi status order tidak diizinkan.',
            httpStatus: 422,
        )->withContext(['from' => $from, 'to' => $to]);
    }

    public static function notFound(): self
    {
        return self::make(
            'not_found',
            'Order tidak ditemukan.',
            httpStatus: 404,
        );
    }

    public static function notOwner(): self
    {
        return self::make(
            'not_owner',
            'Anda bukan pemilik order ini.',
            httpStatus: 403,
        );
    }

    public static function maxClaimsReached(): self
    {
        return self::make(
            'max_claims_reached',
            'Kuota mitra untuk order ini sudah penuh.',
            httpStatus: 409,
        );
    }
}
