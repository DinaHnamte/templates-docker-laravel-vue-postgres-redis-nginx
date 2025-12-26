<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Bid>
 */
class BidFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'driver_id' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 5, 20),
            'distance_km' => $this->faker->randomFloat(2, 1, 15),
            'eta_minutes' => $this->faker->numberBetween(5, 45),
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ];
    }
}

