<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startTime = sprintf('%02d:00', $this->faker->numberBetween(8, 22));
        $endTime = Carbon::createFromFormat('H:i', $startTime)->addHour()->format('H:i');

        return [
            'tenant_id' => Tenant::factory(),
            'court_id' => Court::factory(),
            'date' => $this->faker->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'cancelled']),
            'price' => 0,
            'is_light_required' => false,
            'light_surcharge' => 0,
            'booking_reference' => 'A' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
