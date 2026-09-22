<?php

namespace Database\Factories;

use App\Models\SfRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfRecord>
 */
class SfRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user' => 'Windy',
            'sf_number' => fake()->unique()->numberBetween(30000000, 39999999),
            'vnid' => 'VN'.fake()->numerify('######'),
            'customer_name' => fake()->company(),
            'service' => fake()->randomElement(['Dedicated Internet', 'Broadband', 'IP Transit', 'Metro Ethernet']),
        ];
    }
}
