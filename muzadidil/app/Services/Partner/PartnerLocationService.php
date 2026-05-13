<?php

namespace App\Services\Partner;

use App\Exceptions\Partner\PartnerException;
use App\Models\Order;
use App\Models\PartnerLocation;
use App\Models\User;
use Illuminate\Support\Collection;

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

    /**
     * Find verified, online, category-serving partners within the given radius of
     * the order's pickup location. When $excludePreviousRadius is set, partners
     * already covered by that smaller radius are excluded — used by V2 staged
     * broadcasting so each partner is notified at most once per order.
     *
     * @return Collection<int, User>
     */
    public function findPartnersInRadius(
        Order $order,
        int $radiusKm,
        ?int $excludePreviousRadius = null,
    ): Collection {
        $pickup = $order->pickupLatLng();
        if ($pickup === null) {
            return collect();
        }

        $lat = $pickup['lat'];
        $lng = $pickup['lng'];
        $categorySlug = $order->serviceCategory->slug;

        $query = User::query()
            ->where('role', User::ROLE_PARTNER)
            ->whereNull('blocked_at')
            ->whereHas('partnerProfile', fn ($q) => $q
                ->where('is_verified', true)
                ->whereJsonContains('service_categories', $categorySlug)
            )
            ->whereHas('partnerLocation', function ($q) use ($lat, $lng, $radiusKm, $excludePreviousRadius) {
                $q->where('is_online', true)
                    ->where('last_seen_at', '>', now()->subMinutes(5))
                    ->whereRaw(
                        'ST_Distance_Sphere(coordinates, ST_GeomFromText(?)) <= ?',
                        [sprintf('POINT(%F %F)', $lng, $lat), $radiusKm * 1000]
                    );

                if ($excludePreviousRadius !== null) {
                    $q->whereRaw(
                        'ST_Distance_Sphere(coordinates, ST_GeomFromText(?)) > ?',
                        [sprintf('POINT(%F %F)', $lng, $lat), $excludePreviousRadius * 1000]
                    );
                }
            })
            ->with('partnerLocation');

        return $query->get();
    }
}
