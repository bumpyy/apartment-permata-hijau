<?php

use App\Models\Tenant;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a court
    $this->court = Court::factory()->create([
        'name' => 'Court 2',
        'light_surcharge' => 50000,
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
    $response = $this->actingAs($this->admin)
        ->get(route('admin.bookings'));

    $response->assertSuccessful();
    $response->assertSee('ADMIN - CREATE BOOKING');
})->skip('admin panel not ready yet');

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

    $response = $this->actingAs($this->admin)
        ->post('/livewire/update', [
            'fingerprint' => [
                'id' => 'pages.admin.bookings',
                'name' => 'pages.admin.bookings',
                'locale' => 'en',
                'path' => 'admin/bookings',
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
})->skip('admin panel not ready yet');

test('admin can deny pending bookings', function () {
    // Create a pending booking
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => '2025-06-01',
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => 'pending',
    ]);

    $response = $this->actingAs($this->admin)
        ->post('/livewire/update', [
            'fingerprint' => [
                'id' => 'pages.admin.bookings',
                'name' => 'pages.admin.bookings',
                'locale' => 'en',
                'path' => 'admin/bookings',
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
                        'method' => 'denyBooking',
                        'params' => [$booking->id],
                    ],
                ],
            ],
        ]);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'cancelled',
        'approved_by' => $this->admin->id,
    ]);
})->skip('admin panel not ready yet');

test('admin can create booking for tenant', function () {
    $response = $this->actingAs($this->admin)
        ->post('/livewire/update', [
            'fingerprint' => [
                'id' => 'pages.admin.create-booking',
                'name' => 'pages.admin.create-booking',
                'locale' => 'en',
                'path' => 'admin/create-booking',
                'method' => 'GET',
            ],
            'serverMemo' => [
                'children' => [],
                'errors' => [],
                'htmlHash' => 'test',
                'data' => [
                    'selectedCourt' => 2,
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
        'court_id' => 2,
        'date' => '2025-06-16',
        'start_time' => '19:00',
        'end_time' => '20:00',
        'status' => 'confirmed',
        'is_light_required' => true,
        'approved_by' => $this->admin->id,
    ]);
})->skip('admin panel not ready yet');

test('admin can select tenant and load their details', function () {
    $response = $this->actingAs($this->admin)
        ->post('/livewire/update', [
            'fingerprint' => [
                'id' => 'pages.admin.create-booking',
                'name' => 'pages.admin.create-booking',
                'locale' => 'en',
                'path' => 'admin/create-booking',
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
})->skip('admin panel not ready yet');

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
