<?php

namespace Database\Factories;

use App\Models\PartnerWallet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerWallet>
 */
class PartnerWalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->partner(),
            'balance' => 0,
        ];
    }

    public function withBalance(int $balance): static
    {
        return $this->state(fn () => ['balance' => $balance]);
    }
}
