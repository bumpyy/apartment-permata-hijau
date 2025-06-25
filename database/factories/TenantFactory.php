<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $name = $this->faker->name();
        $email = strtolower(str_replace(' ', '.', $name)).'@example.com';

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $this->faker->optional()->phoneNumber(),
            'email_verified_at' => $this->faker->optional()->dateTimeBetween('-1 year', 'now'),
            'password' => bcrypt('password'),
            'booking_limit' => 3,
            'is_active' => true,
        ];
    }
}
