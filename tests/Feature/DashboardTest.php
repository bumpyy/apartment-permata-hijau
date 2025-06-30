<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'booking_limit' => 3,
    ]);
    $this->court = Court::factory()->create([
        'name' => 'Court 1',
        'hourly_rate' => 100000,
        'light_surcharge' => 50000,
    ]);
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
});

test('tenant can view dashboard when authenticated', function () {
    $response = $this->actingAs($this->tenant, 'tenant')
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertSee('Dashboard');
});

test('tenant cannot view dashboard when not authenticated', function () {
    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('dashboard shows upcoming bookings', function () {
    $upcomingBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee($upcomingBooking->court->name)
        ->assertSee($upcomingBooking->date->format('d M Y'))
        ->assertSee('10:00 - 11:00');
});

test('dashboard shows past bookings', function () {
    $pastBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::yesterday(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->set('activeTab', 'past')
        ->assertSee($pastBooking->court->name)
        ->assertSee($pastBooking->date->format('d M Y'));
});

test('dashboard shows booking quota information', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('3') // Total booking limit
        ->assertSee('0') // Used bookings
        ->assertSee('3'); // Remaining bookings
});

test('dashboard shows correct quota after bookings', function () {
    // Create 2 confirmed bookings
    Booking::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('2') // Used bookings
        ->assertSee('1'); // Remaining bookings
});

test('dashboard excludes cancelled bookings from quota', function () {
    // Create 1 confirmed and 1 cancelled booking
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'status' => 'confirmed',
    ]);

    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow()->addDay(),
        'status' => 'cancelled',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('1') // Used bookings (cancelled not counted)
        ->assertSee('2'); // Remaining bookings
});

test('dashboard shows free and premium booking quotas', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('Free Bookings')
        ->assertSee('Premium Bookings')
        ->assertSee('3') // Free quota
        ->assertSee('12'); // Premium quota (3 * 4 weeks)
});

test('dashboard shows booking statistics', function () {
    // Create bookings with different statuses
    Booking::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => 'confirmed',
    ]);

    Booking::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => 'pending',
    ]);

    Booking::factory()->count(1)->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => 'cancelled',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('6') // Total bookings
        ->assertSee('3') // Confirmed bookings
        ->assertSee('2') // Pending bookings
        ->assertSee('1'); // Cancelled bookings
});

test('dashboard shows light requirement for evening bookings', function () {
    $eveningBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'start_time' => '19:00',
        'end_time' => '20:00',
        'status' => 'confirmed',
        'is_light_required' => true,
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('Light required')
        ->assertSee('+50k');
});

test('dashboard shows booking type badges', function () {
    $freeBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'booking_type' => 'free',
        'status' => 'confirmed',
    ]);

    $premiumBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'booking_type' => 'premium',
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('FREE')
        ->assertSee('PREMIUM');
});

test('dashboard shows booking reference numbers', function () {
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'booking_reference' => 'A0001',
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('A0001');
});

test('dashboard can switch between tabs', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->set('activeTab', 'upcoming')
        ->assertSet('activeTab', 'upcoming')
        ->set('activeTab', 'past')
        ->assertSet('activeTab', 'past');
});

test('dashboard shows empty state when no bookings', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('No upcoming bookings')
        ->set('activeTab', 'past')
        ->assertSee('No past bookings');
});

test('dashboard shows tenant information', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee($this->tenant->name)
        ->assertSee($this->tenant->email);
});

test('dashboard shows booking status badges', function () {
    $pendingBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => 'pending',
    ]);

    $confirmedBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => 'confirmed',
    ]);

    $cancelledBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => 'cancelled',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('PENDING')
        ->assertSee('CONFIRMED')
        ->set('activeTab', 'past')
        ->assertSee('CANCELLED');
});

test('dashboard shows booking notes when available', function () {
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'notes' => 'Special request for equipment',
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('Special request for equipment');
});

test('dashboard shows booking price information', function () {
    $paidBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'price' => 100000,
        'light_surcharge' => 50000,
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('150,000') // Total price
        ->assertSee('100,000') // Base price
        ->assertSee('50,000'); // Light surcharge
});

test('dashboard shows free booking when no cost', function () {
    $freeBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'price' => 0,
        'light_surcharge' => 0,
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('FREE');
});

test('dashboard loads bookings efficiently', function () {
    // Create multiple bookings to test loading performance
    Booking::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => 'confirmed',
    ]);

    $startTime = microtime(true);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard');

    $endTime = microtime(true);
    $loadTime = $endTime - $startTime;

    // Should load within 2 seconds
    expect($loadTime)->toBeLessThan(2.0);
});

test('dashboard shows correct date formats', function () {
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::parse('2025-06-15'),
        'start_time' => '14:30',
        'end_time' => '15:30',
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('15 Jun 2025')
        ->assertSee('14:30 - 15:30');
});

test('dashboard handles timezone correctly', function () {
    // Set timezone to Asia/Jakarta
    config(['app.timezone' => 'Asia/Jakarta']);

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::parse('2025-06-15'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('15 Jun 2025')
        ->assertSee('10:00 - 11:00');
});
