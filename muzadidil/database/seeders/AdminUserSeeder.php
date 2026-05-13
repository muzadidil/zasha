<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '081200000000'],
            [
                'name' => 'Admin Zasha',
                'email' => 'admin@zasha.local',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'phone_verified_at' => now(),
            ],
        );
    }
}
