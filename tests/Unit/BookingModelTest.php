<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->court = Court::factory()->create([
        'name' => 'Court 2',
        'light_surcharge' => 50000,
        'hourly_rate' => 0,
    ]);

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
});

test('booking can generate reference number', function () {
    $booking = Booking::factory()->create(['id' => 12]);
    expect($booking->generateReference())->toBe('A0012');

    $booking = Booking::factory()->create(['id' => 123]);
    expect($booking->generateReference())->toBe('A0123');

    $booking = Booking::factory()->create(['id' => 1]);
    expect($booking->generateReference())->toBe('A0001');
});

test('booking can calculate price with light surcharge', function () {
    $booking = Booking::factory()->create([
        'court_id' => $this->court->id,
        'start_time' => '19:00',
        'end_time' => '20:00',
    ]);

    $totalPrice = $booking->calculatePrice();
    expect($totalPrice)->toBe(50000);
    expect($booking->is_light_required)->toBeTrue();
    expect($booking->light_surcharge)->toBe(50000);

    $booking2 = Booking::factory()->create([
        'court_id' => $this->court->id,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    $totalPrice2 = $booking2->calculatePrice();
    expect($totalPrice2)->toBe(0);
    expect($booking2->is_light_required)->toBeFalse();
    expect($booking2->light_surcharge)->toBe(0);
});

test('booking has correct relationships', function () {
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'approved_by' => $this->user->id,
    ]);

    expect($booking->tenant->id)->toBe($this->tenant->id);
    expect($booking->court->id)->toBe($this->court->id);
    expect($booking->approver->id)->toBe($this->user->id);
});

test('booking total price attribute works correctly', function () {
    $booking = Booking::factory()->create([
        'price' => 0,
        'light_surcharge' => 50000,
    ]);

    expect($booking->total_price)->toBe(50000);

    $booking->price = 10000;
    expect($booking->total_price)->toBe(60000);
});
