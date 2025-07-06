<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a court
    $this->court = Court::factory()->create([
        'name' => 'Court 2',
        'light_surcharge' => 50000,
        'hourly_rate' => 100000,
    ]);

    // Create a tenant
    $this->tenant = Tenant::factory()->create([
        'tenant_id' => 'tenant#164',
        'booking_limit' => 5,
    ]);

    // Create an admin user
    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    // Set the current date for consistent testing
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
});

test('admin can view bookings page', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.list'));

    $response->assertSuccessful();
    // $response->assertSee('Bookings');
});

test('admin can view create booking page', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.create'));

    $response->assertSuccessful();
    // $response->assertSee('Create Booking');
});

test('admin can create a booking', function () {
    $court = Court::factory()->create();
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($this->admin, 'admin')
        ->post(route('admin.booking.store'), [
            'court_id' => $court->id,
            'tenant_id' => $tenant->id,
            'date' => '2025-06-02',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'notes' => 'Test booking',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('bookings', [
        'court_id' => $court->id,
        'tenant_id' => $tenant->id,
        'date' => '2025-06-02',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'notes' => 'Test booking',
    ]);
});

test('admin can confirm a booking', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'court_id' => $this->court->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->patch(route('admin.booking.confirm', $booking->id));

    $response->assertRedirect();
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'confirmed',
        'approved_by' => $this->admin->id,
    ]);
});

test('admin can cancel a booking with reason', function () {
    $booking = Booking::factory()->create([
        'status' => 'confirmed',
        'court_id' => $this->court->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->patch(route('admin.booking.cancel', $booking->id), [
            'cancellation_reason' => 'Court maintenance required',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'cancelled',
        'cancelled_by' => $this->admin->id,
        'cancellation_reason' => 'Court maintenance required',
    ]);
});

test('admin can view today\'s bookings', function () {
    // Create a booking for today
    $todayBooking = Booking::factory()->create([
        'date' => Carbon::today(),
        'court_id' => $this->court->id,
        'tenant_id' => $this->tenant->id,
        'status' => 'confirmed',
    ]);

    // Create a booking for tomorrow
    $tomorrowBooking = Booking::factory()->create([
        'date' => Carbon::tomorrow(),
        'court_id' => $this->court->id,
        'tenant_id' => $this->tenant->id,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.list'));

    $response->assertSuccessful();
    $response->assertSee($todayBooking->tenant->name);
    $response->assertSee($tomorrowBooking->tenant->name);
});

test('admin can view upcoming bookings', function () {
    // Create bookings for the next few days
    $upcomingBookings = collect([
        Carbon::tomorrow(),
        Carbon::tomorrow()->addDay(),
        Carbon::tomorrow()->addDays(2),
    ])->map(function ($date) {
        return Booking::factory()->create([
            'date' => $date,
            'court_id' => $this->court->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'confirmed',
        ]);
    });

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.list'));

    $response->assertSuccessful();
    $upcomingBookings->each(function ($booking) use ($response) {
        $response->assertSee($booking->tenant->name);
    });
});

test('admin can filter bookings by status', function () {
    // Create bookings with different statuses
    Booking::factory()->create(['status' => 'pending']);
    Booking::factory()->create(['status' => 'confirmed']);
    Booking::factory()->create(['status' => 'cancelled']);

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.list').'?status=pending');

    $response->assertSuccessful();
    $response->assertSee('pending');
    $response->assertDontSee('confirmed');
    $response->assertDontSee('cancelled');
});

test('admin can search bookings by tenant name', function () {
    $tenant = Tenant::factory()->create(['name' => 'John Doe']);
    $booking = Booking::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.list').'?search=John');

    $response->assertSuccessful();
    $response->assertSee('John Doe');
});

test('evening bookings automatically set light requirement', function () {
    $booking = Booking::factory()->create([
        'start_time' => '19:00',
        'court_id' => $this->court->id,
    ]);

    $totalPrice = $booking->calculatePrice();
    expect($booking->is_light_required)->toBeTrue();

    $booking2 = Booking::factory()->create([
        'start_time' => '10:00',
        'court_id' => $this->court->id,
    ]);

    $totalPrice2 = $booking2->calculatePrice();
    expect($booking2->is_light_required)->toBeFalse();
});

test('admin cannot access booking pages without authentication', function () {
    $response = $this->get(route('admin.booking.list'));
    $response->assertRedirect('admin/login');

    $response = $this->get(route('admin.booking.create'));
    $response->assertRedirect('admin/login');
});

test('admin can view booking statistics', function () {
    // Create bookings with different statuses
    Booking::factory()->count(3)->create(['status' => 'confirmed']);
    Booking::factory()->count(2)->create(['status' => 'pending']);
    Booking::factory()->count(1)->create(['status' => 'cancelled']);

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.list'));

    $response->assertSuccessful();
    $response->assertSee('6'); // Total bookings
    $response->assertSee('3'); // Confirmed bookings
    $response->assertSee('2'); // Pending bookings
    $response->assertSee('1'); // Cancelled bookings
});

test('admin can view cancellation reason in booking details', function () {
    $booking = Booking::factory()->create([
        'status' => 'cancelled',
        'cancellation_reason' => 'Court maintenance required',
        'cancelled_by' => $this->admin->id,
        'cancelled_at' => Carbon::now(),
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.list'));

    $response->assertSuccessful();
    $response->assertSee('Court maintenance required');
});

test('admin can toggle today\'s bookings visibility', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.list'));

    $response->assertSuccessful();
    $response->assertSee('Today\'s Bookings');
    $response->assertSee('Hide');
});

test('bookings are grouped by date and court for better UX', function () {
    $court1 = Court::factory()->create(['name' => 'Court 1']);
    $court2 = Court::factory()->create(['name' => 'Court 2']);

    $booking1 = Booking::factory()->create([
        'date' => Carbon::today(),
        'court_id' => $court1->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $booking2 = Booking::factory()->create([
        'date' => Carbon::today(),
        'court_id' => $court2->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->get(route('admin.booking.list'));

    $response->assertSuccessful();
    $response->assertSee('Court 1');
    $response->assertSee('Court 2');
});

test('admin can use cancellation modal with reason', function () {
    $booking = Booking::factory()->create([
        'status' => 'confirmed',
        'court_id' => $this->court->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->post(route('admin.booking.open-cancel-modal', $booking->id));

    $response->assertSuccessful();
    // The modal should be shown with the booking details
});

test('admin can confirm cancellation with reason', function () {
    $booking = Booking::factory()->create([
        'status' => 'confirmed',
        'court_id' => $this->court->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->post(route('admin.booking.confirm-cancellation'), [
            'booking_id' => $booking->id,
            'cancellation_reason' => 'Emergency maintenance',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'cancelled',
        'cancellation_reason' => 'Emergency maintenance',
        'cancelled_by' => $this->admin->id,
    ]);
});
