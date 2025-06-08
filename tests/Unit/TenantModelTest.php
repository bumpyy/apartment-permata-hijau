<?php

use App\Models\Tenant;
use App\Models\Booking;
use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->court = Court::factory()->create();
});

test('tenant auto-generates tenant_id on creation', function () {
    $tenant = Tenant::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    expect($tenant->tenant_id)->toStartWith('tenant#');
    expect($tenant->tenant_id)->toMatch('/^tenant#\d{3}$/');
});

test('tenant respects explicit tenant_id', function () {
    $tenant = Tenant::create([
        'tenant_id' => 'tenant#999',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    expect($tenant->tenant_id)->toBe('tenant#999');
});

test('tenant has correct display name', function () {
    $tenant = Tenant::factory()->create([
        'tenant_id' => 'tenant#164',
        'name' => 'John Doe',
    ]);

    expect($tenant->display_name)->toBe('tenant#164');

    $tenant->tenant_id = null;
    expect($tenant->display_name)->toBe('John Doe');
});

test('tenant can calculate remaining bookings correctly', function () {
    $tenant = Tenant::factory()->create([
        'booking_limit' => 3,
    ]);

    expect($tenant->remaining_weekly_quota)->toBe(3);

    // Create 2 confirmed bookings for future dates in current week
    $weekStart = Carbon::today()->startOfWeek();

    Booking::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->court->id,
        'date' => $weekStart->copy()->addDays(1),
        'booking_week_start' => $weekStart->format('Y-m-d'),
        'status' => 'confirmed',
    ]);

    // Create 1 cancelled booking (shouldn't count)
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->court->id,
        'date' => $weekStart->copy()->addDays(2),
        'booking_week_start' => $weekStart->format('Y-m-d'),
        'status' => 'cancelled',
    ]);

    // Create 1 past booking (shouldn't count for current week)
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::yesterday(),
        'booking_week_start' => Carbon::yesterday()->startOfWeek()->format('Y-m-d'),
        'status' => 'confirmed',
    ]);

    // Refresh the tenant model
    $tenant->refresh();

    expect($tenant->remaining_weekly_quota)->toBe(3);
});


test('tenant can calculate remaining combined bookings', function () {
    $tenant = Tenant::factory()->create(['booking_limit' => 3]);

    expect($tenant->combined_booking_quota['remaining'])->toBe(3);

    // Create some bookings
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->court->id,
        'date' => now()->addDay(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'status' => 'confirmed',
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->court->id,
        'date' => now()->addDays(2),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'status' => 'pending',
    ]);

    // Refresh the model to get updated relationships
    $tenant->refresh();

    expect($tenant->combined_booking_quota['remaining'])->toBe(1);
});
