<?php

namespace Database\Seeders;

use App\Models\Tenant;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        Tenant::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Create test tenant with explicit tenant_id
        Tenant::create([
            'tenant_id' => 'tenant#164',
            'name' => 'John Doe',
            'email' => 'tenant@example.com',
            'phone' => '0818 888 8888',
            'password' => bcrypt('password'),
            'booking_limit' => 5,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Tenant::factory(10)->create();

        $this->call([
            RoleAndUserSeeder::class,
            CourtSeeder::class,
        ]);
    }
}
