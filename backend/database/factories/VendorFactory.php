<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(5),
            'description' => $this->faker->sentence(),
            'formatted_address' => $this->faker->streetAddress(),
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'base_delivery_fee' => $this->faker->randomFloat(2, 0, 10),
            'min_order_total' => $this->faker->randomFloat(2, 0, 20),
            'allow_cod' => true,
            'is_active' => true,
        ];
    }
}

