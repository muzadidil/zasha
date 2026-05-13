<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'min_price' => $this->min_price,
            'price_step' => $this->price_step,
            'max_partners' => $this->max_partners,
            'requires_geolocation' => $this->requires_geolocation,
            'search_radius_km' => $this->search_radius_km,
            'search_timeout_minutes' => $this->search_timeout_minutes,
            'commission_percent' => (float) $this->commission_percent,
        ];
    }
}
