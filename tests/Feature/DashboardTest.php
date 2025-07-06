<?php

use App\Enum\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Settings\SiteSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Livewire\Livewire;
use Tests\TestCase;

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
        ->assertSee('Free Booking Used')
        ->assertSee('Premium Booking Used')
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

test('dashboard shows site settings information', function () {
    $settings = app(SiteSettings::class);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('System Limits')
        ->assertSee((string) $settings->max_bookings_per_tenant)
        ->assertSee((string) $settings->booking_advance_days)
        ->assertSee((string) $settings->cancellation_hours_limit);
});

test('dashboard shows booking policy information', function () {
    $settings = app(SiteSettings::class);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('Booking Cancellation Policy')
        ->assertSee('Booking System Limits')
        ->assertSee((string) $settings->cancellation_hours_limit . ' hours')
        ->assertSee((string) $settings->max_bookings_per_tenant . ' bookings')
        ->assertSee((string) $settings->booking_advance_days . ' days');
});

test('dashboard respects cancellation hours limit', function () {
    $settings = app(SiteSettings::class);
    $settings->cancellation_hours_limit = 24;
    $settings->allow_booking_cancellations = true;
    $settings->save();

    // Create a booking that's exactly 24 hours away
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'start_time' => Carbon::now()->addHours(24),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('Cancel')
        ->assertSee('24 hours');
});

test('dashboard prevents cancellation when limit exceeded', function () {
    $settings = app(SiteSettings::class);
    $settings->cancellation_hours_limit = 24;
    $settings->allow_booking_cancellations = true;
    $settings->save();

    // Create a booking that's less than 24 hours away
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'start_time' => Carbon::now()->addHours(12), // Only 12 hours away
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('You have 12 hours remaining')
        ->assertDontSee('Cancel'); // Should show disabled cancel button
});

test('dashboard prevents cancellation when disabled', function () {
    $settings = app(SiteSettings::class);
    $settings->allow_booking_cancellations = false;
    $settings->save();

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('Booking Cancellations Temporarily Disabled')
        ->assertDontSee('Cancel');
});

test('dashboard allows cancellation when conditions met', function () {
    $settings = app(SiteSettings::class);
    $settings->cancellation_hours_limit = 24;
    $settings->allow_booking_cancellations = true;
    $settings->save();

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow()->addDay(), // 48 hours away
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->call('confirmCancellation')
        ->assertSessionHas('message', 'Booking cancelled successfully! Your quota has been updated.');
});

test('dashboard prevents cancellation of past bookings', function () {
    $settings = app(SiteSettings::class);
    $settings->allow_booking_cancellations = true;
    $settings->save();

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::yesterday(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->assertSessionHas('error', 'Cannot cancel past bookings.');
});

test('dashboard prevents cancellation of already cancelled bookings', function () {
    $settings = app(SiteSettings::class);
    $settings->allow_booking_cancellations = true;
    $settings->save();

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'status' => 'cancelled',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->assertSessionHas('error', 'This booking has already been cancelled.');
});

test('dashboard shows error for unauthorized cancellation', function () {
    $otherTenant = Tenant::factory()->create();
    $booking = Booking::factory()->create([
        'tenant_id' => $otherTenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->assertSessionHas('error', 'Booking not found or access denied.');
});

test('dashboard tab switching works', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSet('activeTab', 'upcoming')
        ->call('setActiveTab', 'past')
        ->assertSet('activeTab', 'past');
});

test('dashboard shows empty states', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee('No upcoming bookings')
        ->set('activeTab', 'past')
        ->assertSee('No booking history');
});

test('dashboard loads without livewire property type errors', function () {
    // This test ensures that the dashboard component loads without the Livewire property type error
    // that was caused by storing SiteSettings object as a public property

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertOk()
        ->assertSee('Welcome back')
        ->assertSee('System Limits')
        ->assertSee('Booking Cancellation Policy');
});

test('dashboard shows cancellation modal when cancel button is clicked', function () {
    $settings = app(SiteSettings::class);
    $settings->cancellation_hours_limit = 24;
    $settings->allow_booking_cancellations = true;
    $settings->save();

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow()->addDay(), // 48 hours away
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->assertSet('showCancelModal', true)
        ->assertSet('bookingToCancel.id', $booking->id)
        ->assertSee('Cancel Booking')
        ->assertSee('Court ' . $booking->court->name)
        ->assertSee('Confirm Cancellation');
});

test('dashboard closes cancellation modal', function () {
    $settings = app(SiteSettings::class);
    $settings->cancellation_hours_limit = 24;
    $settings->allow_booking_cancellations = true;
    $settings->save();

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow()->addDay(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->assertSet('showCancelModal', true)
        ->call('closeCancelModal')
        ->assertSet('showCancelModal', false)
        ->assertSet('bookingToCancel', null)
        ->assertSet('cancellationReason', '');
});

test('dashboard confirms cancellation with reason', function () {
    $settings = app(SiteSettings::class);
    $settings->cancellation_hours_limit = 24;
    $settings->allow_booking_cancellations = true;
    $settings->save();

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow()->addDay(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->set('cancellationReason', 'Change of plans')
        ->call('confirmCancellation')
        ->assertSessionHas('message', 'Booking cancelled successfully! Your quota has been updated.')
        ->assertSet('showCancelModal', false);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatusEnum::CANCELLED);
    expect($booking->cancellation_reason)->toBe('Change of plans');
    expect($booking->cancelled_by)->toBe($this->tenant->id);
    expect($booking->cancelled_at)->not->toBeNull();
});

test('dashboard confirms cancellation without reason', function () {
    $settings = app(SiteSettings::class);
    $settings->cancellation_hours_limit = 24;
    $settings->allow_booking_cancellations = true;
    $settings->save();

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow()->addDay(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->call('confirmCancellation')
        ->assertSessionHas('message', 'Booking cancelled successfully! Your quota has been updated.');

    $booking->refresh();
    expect($booking->cancellation_reason)->toBe('Cancelled by tenant');
});

test('dashboard prevents opening modal for non-cancellable booking', function () {
    $settings = app(SiteSettings::class);
    $settings->cancellation_hours_limit = 24;
    $settings->allow_booking_cancellations = true;
    $settings->save();

    // Create a booking that's less than 24 hours away
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'start_time' => Carbon::now()->addHours(12), // Only 12 hours away
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->assertSessionHas('error')
        ->assertSet('showCancelModal', false);
});

test('dashboard prevents opening modal for unauthorized booking', function () {
    $otherTenant = Tenant::factory()->create();
    $booking = Booking::factory()->create([
        'tenant_id' => $otherTenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->assertSessionHas('error', 'Booking not found or access denied.')
        ->assertSet('showCancelModal', false);
});

test('dashboard modal shows booking details correctly', function () {
    $settings = app(SiteSettings::class);
    $settings->cancellation_hours_limit = 24;
    $settings->allow_booking_cancellations = true;
    $settings->save();

    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow()->addDay(),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'booking_type' => 'premium',
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('openCancelModal', $booking->id)
        ->assertSee('Court ' . $booking->court->name)
        ->assertSee('10:00 AM - 11:00 AM')
        ->assertSee('⭐ Premium')
        ->assertSee('✅ Confirmed')
        ->assertSee('This action cannot be undone')
        ->assertSee('Your booking quota will be restored');
});
