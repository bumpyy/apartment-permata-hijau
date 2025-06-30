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

test('admin can confirm pending bookings', function () {
    // Create a pending booking
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => '2025-06-01',
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->post('/livewire/update', [
            'fingerprint' => [
                'id' => 'pages.admin.booking',
                'name' => 'pages.admin.booking',
                'locale' => 'en',
                'path' => 'admin/booking',
                'method' => 'GET',
            ],
            'serverMemo' => [
                'children' => [],
                'errors' => [],
                'htmlHash' => 'test',
                'data' => [],
                'dataMeta' => [],
                'checksum' => 'test',
            ],
            'updates' => [
                [
                    'type' => 'callMethod',
                    'payload' => [
                        'method' => 'confirmBooking',
                        'params' => [$booking->id],
                    ],
                ],
            ],
        ]);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'confirmed',
        'approved_by' => $this->admin->id,
    ]);
});

test('admin can cancel pending bookings', function () {
    // Create a pending booking
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => '2025-06-01',
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->post('/livewire/update', [
            'fingerprint' => [
                'id' => 'pages.admin.booking',
                'name' => 'pages.admin.booking',
                'locale' => 'en',
                'path' => 'admin/booking',
                'method' => 'GET',
            ],
            'serverMemo' => [
                'children' => [],
                'errors' => [],
                'htmlHash' => 'test',
                'data' => [],
                'dataMeta' => [],
                'checksum' => 'test',
            ],
            'updates' => [
                [
                    'type' => 'callMethod',
                    'payload' => [
                        'method' => 'cancelBooking',
                        'params' => [$booking->id],
                    ],
                ],
            ],
        ]);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'cancelled',
        'cancelled_by' => $this->admin->id,
    ]);
});

test('admin can create booking for tenant', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->post('/livewire/update', [
            'fingerprint' => [
                'id' => 'pages.admin.booking.create',
                'name' => 'pages.admin.booking.create',
                'locale' => 'en',
                'path' => 'admin/booking/create',
                'method' => 'GET',
            ],
            'serverMemo' => [
                'children' => [],
                'errors' => [],
                'htmlHash' => 'test',
                'data' => [
                    'selectedCourt' => $this->court->id,
                    'selectedDate' => '16 June 2025',
                    'selectedTime' => '19:00 - 20:00',
                    'selectedTenant' => $this->tenant->id,
                    'tenantName' => $this->tenant->name,
                    'tenantPhone' => $this->tenant->phone,
                    'isLightRequired' => true,
                ],
                'dataMeta' => [],
                'checksum' => 'test',
            ],
            'updates' => [
                [
                    'type' => 'callMethod',
                    'payload' => [
                        'method' => 'confirmBooking',
                        'params' => [],
                    ],
                ],
            ],
        ]);

    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => '2025-06-16',
        'start_time' => '19:00',
        'end_time' => '20:00',
        'status' => 'confirmed',
        'is_light_required' => true,
        'approved_by' => $this->admin->id,
    ]);
});

test('admin can select tenant and load their details', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->post('/livewire/update', [
            'fingerprint' => [
                'id' => 'pages.admin.booking.create',
                'name' => 'pages.admin.booking.create',
                'locale' => 'en',
                'path' => 'admin/booking/create',
                'method' => 'GET',
            ],
            'serverMemo' => [
                'children' => [],
                'errors' => [],
                'htmlHash' => 'test',
                'data' => [],
                'dataMeta' => [],
                'checksum' => 'test',
            ],
            'updates' => [
                [
                    'type' => 'callMethod',
                    'payload' => [
                        'method' => 'selectTenant',
                        'params' => [$this->tenant->id],
                    ],
                ],
            ],
        ]);

    $response->assertSuccessful();
});

test('admin can edit booking details', function () {
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->post('/livewire/update', [
            'fingerprint' => [
                'id' => 'pages.admin.booking',
                'name' => 'pages.admin.booking',
                'locale' => 'en',
                'path' => 'admin/booking',
                'method' => 'GET',
            ],
            'serverMemo' => [
                'children' => [],
                'errors' => [],
                'htmlHash' => 'test',
                'data' => [
                    'editForm' => [
                        'status' => 'confirmed',
                        'is_light_required' => true,
                        'notes' => 'Updated booking',
                    ],
                ],
                'dataMeta' => [],
                'checksum' => 'test',
            ],
            'updates' => [
                [
                    'type' => 'callMethod',
                    'payload' => [
                        'method' => 'updateBooking',
                        'params' => [],
                    ],
                ],
            ],
        ]);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'confirmed',
        'is_light_required' => true,
        'notes' => 'Updated booking',
        'edited_by' => $this->admin->id,
    ]);
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
