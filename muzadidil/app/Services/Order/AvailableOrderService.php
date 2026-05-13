<?php

namespace App\Services\Order;

use App\Exceptions\Partner\PartnerException;
use App\Models\Order;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvailableOrderService
{
    /**
     * Returns orders matching:
     *  - status = searching
     *  - not yet expired
     *  - category is in the partner's served categories
     *  - within the partner's current location radius (when category requires geolocation)
     *
     * @return Collection<int, Order>
     */
    public function listFor(User $partner): Collection
    {
        $profile = $partner->partnerProfile;
        if (! $profile) {
            throw PartnerException::profileMissing();
        }

        $servedSlugs = $profile->service_categories ?? [];
        if (empty($servedSlugs)) {
            return collect();
        }

        $location = $partner->partnerLocation;
        $coords = $location?->latLng();

        $servedCategoryIds = ServiceCategory::whereIn('slug', $servedSlugs)->pluck('id', 'slug');

        $query = Order::query()
            ->with(['serviceCategory', 'customer'])
            ->where('status', Order::STATUS_SEARCHING)
            ->where('expires_at', '>', now())
            ->whereIn('service_category_id', $servedCategoryIds->values());

        // For categories that require geolocation, only include orders whose pickup
        // lies within the category radius from the partner's last known position.
        // We compute distance with ST_Distance_Sphere and filter in SQL.
        if ($coords !== null) {
            $partnerPoint = sprintf("ST_GeomFromText('POINT(%F %F)')", $coords['lng'], $coords['lat']);
            $query->select('orders.*')
                ->selectRaw("ST_Distance_Sphere({$partnerPoint}, pickup_location) AS distance_meters")
                ->join('service_categories', 'service_categories.id', '=', 'orders.service_category_id')
                ->where(function ($q) use ($partnerPoint) {
                    // Non-geolocation categories (WFH) are always included.
                    $q->where('service_categories.requires_geolocation', false)
                        ->orWhereRaw("ST_Distance_Sphere({$partnerPoint}, pickup_location) <= service_categories.search_radius_km * 1000");
                });
        } else {
            // No location yet → only categories that don't require geolocation.
            $query->whereHas('serviceCategory', fn ($q) => $q->where('requires_geolocation', false));
        }

        return $query->orderByDesc('current_price')->limit(50)->get();
    }
}
