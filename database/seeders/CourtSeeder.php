<?php

namespace Database\Seeders;

use App\Models\Court;
use Illuminate\Database\Seeder;

class CourtSeeder extends Seeder
{
    public function run(): void
    {
        $courts = [
            [
                'name' => 'Court 1',
                'description' => 'Main tennis court with professional lighting',
                'hourly_rate' => 0,
                'light_surcharge' => 50000,
                'is_active' => true,
                'operating_hours' => [
                    'open' => '08:00',
                    'close' => '23:00',
                ],
            ],
            [
                'name' => 'Court 2',
                'description' => 'Secondary tennis court with premium surface',
                'hourly_rate' => 0,
                'light_surcharge' => 50000,
                'is_active' => true,
                'operating_hours' => [
                    'open' => '08:00',
                    'close' => '23:00',
                ],
            ],
            [
                'name' => 'Court 3',
                'description' => 'Practice court for training sessions',
                'hourly_rate' => 0,
                'light_surcharge' => 50000,
                'is_active' => true,
                'operating_hours' => [
                    'open' => '08:00',
                    'close' => '23:00',
                ],
            ],
        ];

        foreach ($courts as $court) {
            Court::create($court);
        }
    }
}
