<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestCustomerSetupSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::updateOrCreate(
            ['phone' => '081200000002'],
            [
                'name' => 'Pelanggan Test',
                'email' => 'pelanggan.test@zasha.local',
                'password' => Hash::make('password'),
                'role' => User::ROLE_CUSTOMER,
                'phone_verified_at' => now(),
            ],
        );

        $this->command?->info("Test customer ready: phone=081200000002 password=password id={$customer->id}");
    }
}
