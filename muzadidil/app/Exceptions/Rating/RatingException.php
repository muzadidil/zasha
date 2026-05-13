<?php

namespace App\Exceptions\Rating;

use App\Exceptions\KamusReportableException;

class RatingException extends KamusReportableException
{
    protected string $domain = 'rating';

    public static function orderNotCompleted(): self
    {
        return self::make(
            'order_not_completed',
            'Rating hanya bisa diberikan setelah order selesai.',
            httpStatus: 422,
        );
    }

    public static function notParticipant(): self
    {
        return self::make(
            'not_participant',
            'Anda bukan peserta order ini.',
            httpStatus: 403,
        );
    }

    public static function alreadyRated(): self
    {
        return self::make(
            'already_rated',
            'Anda sudah memberi rating untuk order ini.',
            httpStatus: 409,
        );
    }
}
