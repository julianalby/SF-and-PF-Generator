<?php

namespace Database\Factories;

use App\Models\PfRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PfRecord>
 */
class PfRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user' => 'Windy',
            'pf_number' => fake()->unique()->numberBetween(30000000, 39999999),
            'project_name' => fake()->words(3, true),
            'sf_number' => null,
            'vnid' => null,
            'customer_name' => null,
        ];
    }
}
