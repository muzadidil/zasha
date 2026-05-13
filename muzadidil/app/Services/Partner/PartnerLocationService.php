<?php

namespace App\Services\Partner;

use App\Exceptions\Partner\PartnerException;
use App\Models\PartnerLocation;
use App\Models\User;

class PartnerLocationService
{
    public function updateCoordinates(User $partner, float $lat, float $lng, ?int $accuracy = null): PartnerLocation
    {
        return PartnerLocation::upsertCoordinates(
            userId: $partner->id,
            latitude: $lat,
            longitude: $lng,
            accuracy: $accuracy,
        );
    }

    public function setOnline(User $partner, bool $online): PartnerLocation
    {
        $location = $partner->partnerLocation;

        if (! $location) {
            throw PartnerException::locationMissing();
        }

        $location->is_online = $online;
        $location->last_seen_at = now();
        $location->save();

        return $location;
    }
}
