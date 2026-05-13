<?php

namespace Database\Factories;

use App\Models\PartnerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerProfile>
 */
class PartnerProfileFactory extends Factory
{
    public function definition(): array
    {
        $ktp = fake()->numerify('################');

        return [
            'user_id' => User::factory()->partner(),
            'ktp_number' => $ktp,
            'ktp_number_hash' => hash('sha256', $ktp),
            'ktp_photo_url' => 'https://example.test/ktp/'.fake()->uuid().'.jpg',
            'vehicle_info' => null,
            'skills' => [],
            'service_categories' => ['titip', 'tenaga'],
            'bank_name' => null,
            'bank_account' => null,
            'is_verified' => false,
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }
}
