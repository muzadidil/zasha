<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'partner_id' => $this->partner_id,
            'customer' => $this->whenLoaded('customer', fn () => [
                'name' => $this->customer->name,
                'average_rating' => $this->customer->average_rating !== null
                    ? (float) $this->customer->average_rating
                    : null,
                'rating_count' => (int) $this->customer->rating_count,
            ]),
            'service_category' => $this->whenLoaded('serviceCategory', fn () => [
                'slug' => $this->serviceCategory->slug,
                'name' => $this->serviceCategory->name,
                'requires_geolocation' => (bool) $this->serviceCategory->requires_geolocation,
            ]),
            'details' => $this->details,
            'current_price' => $this->current_price,
            'initial_price' => $this->initial_price,
            'status' => $this->status,
            'active_radius_km' => $this->active_radius_km,
            'current_step_index' => $this->current_step_index,
            'pickup' => $this->pickupLatLng(),
            'destination' => $this->destinationLatLng(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
