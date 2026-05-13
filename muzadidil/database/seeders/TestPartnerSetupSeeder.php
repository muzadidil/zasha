<?php

namespace Database\Seeders;

use App\Models\PartnerLocation;
use App\Models\PartnerProfile;
use App\Models\PartnerWallet;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestPartnerSetupSeeder extends Seeder
{
    public function run(): void
    {
        $jemberLat = -8.1727;
        $jemberLng = 113.7000;

        $partner = User::updateOrCreate(
            ['phone' => '081200000001'],
            [
                'name' => 'Mitra Test Jember',
                'email' => 'mitra.test@zasha.local',
                'password' => Hash::make('password'),
                'role' => User::ROLE_PARTNER,
                'phone_verified_at' => now(),
            ],
        );

        $ktpNumber = '3509000000000001';
        PartnerProfile::updateOrCreate(
            ['user_id' => $partner->id],
            [
                'ktp_number' => $ktpNumber,
                'ktp_number_hash' => hash('sha256', $ktpNumber),
                'ktp_photo_url' => 'https://example.local/ktp/test.jpg',
                'vehicle_info' => ['type' => 'motor', 'plate' => 'P 0001 ZA'],
                'skills' => ['Laravel', 'Vue 3', 'Angkut barang', 'Bersih-bersih'],
                'service_categories' => [
                    ServiceCategory::SLUG_WFH,
                    ServiceCategory::SLUG_TITIP,
                    ServiceCategory::SLUG_TENAGA,
                    ServiceCategory::SLUG_SERVICE,
                ],
                'bank_name' => 'BCA',
                'bank_account' => '1234567890',
                'is_verified' => true,
                'verified_at' => now(),
            ],
        );

        PartnerWallet::updateOrCreate(
            ['user_id' => $partner->id],
            ['balance' => 50_000],
        );

        $location = PartnerLocation::upsertCoordinates(
            userId: $partner->id,
            latitude: $jemberLat,
            longitude: $jemberLng,
        );
        $location->is_online = true;
        $location->last_seen_at = now();
        $location->save();

        $this->command?->info("Test partner ready: phone=081200000001 password=password id={$partner->id} @ Jember ({$jemberLat}, {$jemberLng})");
    }
}
