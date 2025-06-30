<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
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
    $tenant = Tenant::factory()->create(['booking_limit' => 3]);

    // Set test time to ensure consistent week calculation
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
    $weekStart = Carbon::now()->startOfWeek();

    // Create bookings for the same week
    $bookings = [
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'date' => $weekStart->copy()->addDays(1),
            'booking_week_start' => $weekStart->format('Y-m-d'),
        ]),
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'date' => $weekStart->copy()->addDays(2),
            'booking_week_start' => $weekStart->format('Y-m-d'),
        ]),
    ];

    foreach ($bookings as $booking) {
        expect($booking->booking_week_start->format('Y-m-d'))->toBe($weekStart->format('Y-m-d'));
    }

    // Re-fetch tenant to clear relationship cache
    $tenant->refresh();

    $usage = $tenant->getCurrentWeekQuotaUsage();
    expect($usage)->toBe(2);
    expect($tenant->remaining_weekly_quota)->toBe(1);
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

test('tenant can calculate free booking quota', function () {
    $tenant = Tenant::factory()->create(['booking_limit' => 3]);

    expect($tenant->free_booking_quota['remaining'])->toBe(3);

    // Create free bookings
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->court->id,
        'date' => now()->addDay(),
        'booking_type' => 'free',
        'status' => 'confirmed',
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->court->id,
        'date' => now()->addDays(2),
        'booking_type' => 'free',
        'status' => 'pending',
    ]);

    $tenant->refresh();

    expect($tenant->free_booking_quota['remaining'])->toBe(1);
    expect($tenant->free_booking_quota['used'])->toBe(2);
    expect($tenant->free_booking_quota['total'])->toBe(3);
});

test('tenant can calculate premium booking quota', function () {
    $tenant = Tenant::factory()->create(['booking_limit' => 3]);

    expect($tenant->premium_booking_quota['remaining'])->toBe(12); // 3 * 4 weeks

    // Create premium bookings
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->court->id,
        'date' => now()->addDays(10),
        'booking_type' => 'premium',
        'status' => 'confirmed',
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'court_id' => $this->court->id,
        'date' => now()->addDays(15),
        'booking_type' => 'premium',
        'status' => 'pending',
    ]);

    $tenant->refresh();

    expect($tenant->premium_booking_quota['remaining'])->toBe(10);
    expect($tenant->premium_booking_quota['used'])->toBe(2);
    expect($tenant->premium_booking_quota['total'])->toBe(12);
});

test('tenant can check if they can make specific type booking', function () {
    $tenant = Tenant::factory()->create(['booking_limit' => 2]);

    // Set test time to ensure consistent week calculation
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));

    $result = $tenant->canMakeSpecificTypeBooking(now()->addDays(5)->format('Y-m-d'), 'free');
    expect($result['can_book'])->toBeTrue();

    // Test premium booking within 1 month (should be allowed)
    $result = $tenant->canMakeSpecificTypeBooking(now()->addDays(25)->format('Y-m-d'), 'premium');
    expect($result['can_book'])->toBeTrue();

    // Test premium booking beyond 1 month (should be denied)
    $result = $tenant->canMakeSpecificTypeBooking(now()->addDays(35)->format('Y-m-d'), 'premium');
    expect($result['can_book'])->toBeFalse();
    expect($result['reason'])->toBe('Premium booking only available up to 1 month in advance');
});

test('tenant cannot exceed weekly quota', function () {
    $tenant = Tenant::factory()->create(['booking_limit' => 2]);

    // Set test time to ensure consistent week calculation
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
    $weekStart = Carbon::now()->startOfWeek();

    // Create bookings to reach the limit
    $bookings = [
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'date' => $weekStart->copy()->addDays(1),
            'booking_week_start' => $weekStart->format('Y-m-d'),
        ]),
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'date' => $weekStart->copy()->addDays(2),
            'booking_week_start' => $weekStart->format('Y-m-d'),
        ]),
    ];

    // Re-fetch tenant to clear relationship cache
    $tenant->refresh();

    $result = $tenant->canMakeSpecificTypeBooking($weekStart->copy()->addDays(3)->format('Y-m-d'), 'free');
    expect($result['can_book'])->toBeFalse();
    expect($result['available_slots'])->toBe(0);
});

test('tenant can make booking when quota available', function () {
    $tenant = Tenant::factory()->create(['booking_limit' => 3]);

    $result = $tenant->canMakeBooking(1);
    expect($result['can_book'])->toBeTrue();
    expect($result['available_slots'])->toBe(3);
});

test('tenant cannot make booking when quota exceeded', function () {
    $tenant = Tenant::factory()->create(['booking_limit' => 2]);

    $result = $tenant->canMakeBooking(3);
    expect($result['can_book'])->toBeFalse();
    expect($result['available_slots'])->toBe(2);
});

test('tenant weekly quota excludes cancelled bookings', function () {
    $tenant = Tenant::factory()->create(['booking_limit' => 2]);

    // Set test time to ensure consistent week calculation
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
    $weekStart = Carbon::now()->startOfWeek();

    // Create a confirmed booking
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => $weekStart->copy()->addDays(1),
        'booking_week_start' => $weekStart->format('Y-m-d'),
        'status' => \App\Enum\BookingStatusEnum::CONFIRMED,
    ]);

    // Create a cancelled booking (should not count towards quota)
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => $weekStart->copy()->addDays(2),
        'booking_week_start' => $weekStart->format('Y-m-d'),
        'status' => \App\Enum\BookingStatusEnum::CANCELLED,
    ]);

    // Re-fetch tenant to clear relationship cache
    $tenant->refresh();

    expect($tenant->remaining_weekly_quota)->toBe(1);
});

test('tenant can get current week quota usage', function () {
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));

    $tenant = Tenant::factory()->create(['booking_limit' => 3]);
    $weekStart = Carbon::now()->startOfWeek();

    // Create bookings for the current week
    $bookings = [
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'date' => $weekStart->copy()->addDays(1),
            'booking_week_start' => $weekStart->format('Y-m-d'),
            'status' => \App\Enum\BookingStatusEnum::CONFIRMED,
        ]),
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'date' => $weekStart->copy()->addDays(2),
            'booking_week_start' => $weekStart->format('Y-m-d'),
            'status' => \App\Enum\BookingStatusEnum::CONFIRMED,
        ]),
    ];

    // Re-fetch tenant to clear relationship cache
    $tenant->refresh();

    expect($tenant->getCurrentWeekQuotaUsage())->toBe(2);
});

test('tenant relationships work correctly', function () {
    $tenant = Tenant::factory()->create();
    $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

    expect($tenant->bookings)->toHaveCount(1);
    expect($tenant->bookings->first()->id)->toBe($booking->id);
});

test('tenant can be deactivated', function () {
    $tenant = Tenant::factory()->create(['is_active' => true]);

    $tenant->update(['is_active' => false]);

    expect($tenant->is_active)->toBeFalse();
});

test('tenant email verification works', function () {
    $tenant = Tenant::factory()->create(['email_verified_at' => null]);

    expect($tenant->hasVerifiedEmail())->toBeFalse();

    $tenant->markEmailAsVerified();

    expect($tenant->hasVerifiedEmail())->toBeTrue();
});

test('tenant can generate unique tenant_id', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    expect($tenant1->tenant_id)->not->toBe($tenant2->tenant_id);
});

test('tenant booking limit defaults correctly', function () {
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
    $tenant = Tenant::create([
        'name' => 'Test Tenant',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
        'booking_limit' => 3,
    ]);
    expect($tenant->booking_limit)->toBe(3);
});

test('tenant can have multiple bookings on different dates', function () {
    $tenant = Tenant::factory()->create();

    // Create 5 bookings on different dates
    for ($i = 1; $i <= 5; $i++) {
        Booking::factory()->create([
            'tenant_id' => $tenant->id,
            'date' => now()->addDays($i)->format('Y-m-d'),
        ]);
    }

    expect($tenant->bookings)->toHaveCount(5);
});
