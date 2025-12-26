<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'vendor_id' => Vendor::factory(),
            'fulfillment_type' => 'delivery',
            'status' => 'pending_vendor_confirm',
            'subtotal' => $this->faker->randomFloat(2, 10, 50),
            'delivery_fee' => $this->faker->randomFloat(2, 0, 10),
            'service_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => $this->faker->randomFloat(2, 10, 60),
        ];
    }
}

