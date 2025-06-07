<?php

namespace Database\Factories;

use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        return [
            'name' => 'Court ' . $this->faker->unique()->numberBetween(1, 5),
            'description' => $this->faker->optional()->sentence(),
            'hourly_rate' => 0,
            'light_surcharge' => 50000,
            'is_active' => true,
            'operating_hours' => [
                'open' => '08:00',
                'close' => '23:00'
            ],
        ];
    }
}
