<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'slug' => ServiceCategory::SLUG_WFH,
                'name' => 'WFH',
                'min_price' => 50_000,
                'price_step' => 10_000,
                'max_partners' => 1,
                'requires_geolocation' => false,
                'search_radius_km' => null,
                'radius_steps' => null,
                'step_duration_seconds' => 60,
                'search_timeout_minutes' => 1,
                'commission_percent' => 5.00,
            ],
            [
                'slug' => ServiceCategory::SLUG_TITIP,
                'name' => 'Titip',
                'min_price' => 10_000,
                'price_step' => 5_000,
                'max_partners' => 1,
                'requires_geolocation' => true,
                'search_radius_km' => 4,
                'radius_steps' => [1, 2, 3, 4],
                'step_duration_seconds' => 15,
                'search_timeout_minutes' => 1,
                'commission_percent' => 5.00,
            ],
            [
                'slug' => ServiceCategory::SLUG_TENAGA,
                'name' => 'Tenaga',
                'min_price' => 100_000,
                'price_step' => 25_000,
                'max_partners' => 10,
                'requires_geolocation' => true,
                'search_radius_km' => 4,
                'radius_steps' => [1, 2, 3, 4],
                'step_duration_seconds' => 15,
                'search_timeout_minutes' => 1,
                'commission_percent' => 5.00,
            ],
            [
                'slug' => ServiceCategory::SLUG_SERVICE,
                'name' => 'Service',
                'min_price' => 50_000,
                'price_step' => 10_000,
                'max_partners' => 1,
                'requires_geolocation' => true,
                'search_radius_km' => 4,
                'radius_steps' => [1, 2, 3, 4],
                'step_duration_seconds' => 15,
                'search_timeout_minutes' => 1,
                'commission_percent' => 5.00,
            ],
        ];

        foreach ($categories as $row) {
            ServiceCategory::updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
