<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->court = Court::factory()->create([
        'name' => 'Court 2',
        'light_surcharge' => 50000,
        'hourly_rate' => 100000,
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
    expect($totalPrice)->toBe(150000); // 100000 + 50000
    expect($booking->is_light_required)->toBeTrue();
    expect($booking->light_surcharge)->toBe(50000);
    expect($booking->price)->toBe(100000);

    $booking2 = Booking::factory()->create([
        'court_id' => $this->court->id,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    $totalPrice2 = $booking2->calculatePrice();
    expect($totalPrice2)->toBe(100000);
    expect($booking2->is_light_required)->toBeFalse();
    expect($booking2->light_surcharge)->toBe(0);
    expect($booking2->price)->toBe(100000);
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
        'price' => 100000,
        'light_surcharge' => 50000,
    ]);

    expect($booking->total_price)->toBe(150000);

    $booking->price = 200000;
    expect($booking->total_price)->toBe(250000);
});

test('booking status display works correctly', function () {
    $court = Court::factory()->create([
        'hourly_rate' => 0,
        'light_surcharge' => 0,
    ]);

    $freeBooking = Booking::factory()->create([
        'court_id' => $court->id,
        'status' => \App\Enum\BookingStatusEnum::CONFIRMED,
        'start_time' => '10:00',
    ]);
    $freeBooking->calculatePrice();

    // Debug: check what the actual status and total price are
    expect($freeBooking->total_price)->toBe(0);
    expect($freeBooking->status->value)->toBe('confirmed');

    // The issue is that the match statement compares against strings but status is an enum
    // Let me check what the actual status_display returns
    $statusDisplay = $freeBooking->status_display;
    expect($statusDisplay)->toBe('FREE');

    $court2 = Court::factory()->create([
        'hourly_rate' => 100000,
        'light_surcharge' => 50000,
    ]);

    $paidBooking = Booking::factory()->create([
        'court_id' => $court2->id,
        'status' => \App\Enum\BookingStatusEnum::CONFIRMED,
        'start_time' => '19:00',
    ]);
    $paidBooking->calculatePrice();
    expect($paidBooking->status_display)->toBe('PAID');

    $pendingBooking = Booking::factory()->create([
        'status' => \App\Enum\BookingStatusEnum::PENDING,
    ]);
    expect($pendingBooking->status_display)->toBe('PENDING');

    $cancelledBooking = Booking::factory()->create([
        'status' => \App\Enum\BookingStatusEnum::CANCELLED,
    ]);
    expect($cancelledBooking->status_display)->toBe('CANCELLED');
});

test('booking type display works correctly', function () {
    $freeBooking = Booking::factory()->create(['booking_type' => 'free']);
    expect($freeBooking->booking_type_display)->toBe('FREE');

    $premiumBooking = Booking::factory()->create(['booking_type' => 'premium']);
    expect($premiumBooking->booking_type_display)->toBe('PREMIUM');
});

test('booking automatically sets booking week start on creation', function () {
    $booking = Booking::factory()->create([
        'date' => '2025-06-15', // Sunday
    ]);

    expect($booking->booking_week_start->format('Y-m-d'))->toBe('2025-06-09'); // Monday of that week
});

test('booking automatically determines booking type based on date', function () {
    // Free booking (within 7 days)
    $freeBooking = Booking::factory()->create([
        'date' => now()->addDays(5),
        'booking_type' => null,
    ]);
    expect($freeBooking->booking_type)->toBe('free');

    // Premium booking (beyond 7 days)
    $premiumBooking = Booking::factory()->create([
        'date' => now()->addDays(10),
        'booking_type' => null,
    ]);
    expect($premiumBooking->booking_type)->toBe('premium');
});

test('booking respects explicit booking type', function () {
    $booking = Booking::factory()->create([
        'date' => now()->addDays(5),
        'booking_type' => 'premium',
    ]);
    expect($booking->booking_type)->toBe('premium');
});

test('booking can get booked days for tenant', function () {
    $tenant = Tenant::factory()->create();

    // Create bookings for different dates
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => '2025-06-01',
        'status' => 'confirmed',
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => '2025-06-01',
        'status' => 'pending',
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => '2025-06-02',
        'status' => 'confirmed',
    ]);

    // Cancelled booking should not be included
    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => '2025-06-03',
        'status' => 'cancelled',
    ]);

    $bookedDays = Booking::getBookedDaysForTenant($tenant->id);

    expect($bookedDays)->toHaveCount(2); // 2 unique dates
    expect($bookedDays->has('2025-06-01'))->toBeTrue();
    expect($bookedDays->has('2025-06-02'))->toBeTrue();
    expect($bookedDays->has('2025-06-03'))->toBeFalse();
});

test('booking can get booked days with date range', function () {
    $tenant = Tenant::factory()->create();

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => '2025-06-01',
        'status' => 'confirmed',
    ]);

    Booking::factory()->create([
        'tenant_id' => $tenant->id,
        'date' => '2025-06-15',
        'status' => 'confirmed',
    ]);

    $bookedDays = Booking::getBookedDaysForTenant($tenant->id, '2025-06-01', '2025-06-10');

    expect($bookedDays)->toHaveCount(1);
    expect($bookedDays->has('2025-06-01'))->toBeTrue();
    expect($bookedDays->has('2025-06-15'))->toBeFalse();
});

test('booking light requirement is set correctly for evening hours', function () {
    // Evening booking (18:00 and later)
    $eveningBooking = Booking::factory()->create([
        'court_id' => $this->court->id,
        'start_time' => '18:00',
    ]);
    $eveningBooking->calculatePrice();
    expect($eveningBooking->is_light_required)->toBeTrue();

    // Late evening booking
    $lateEveningBooking = Booking::factory()->create([
        'court_id' => $this->court->id,
        'start_time' => '20:00',
    ]);
    $lateEveningBooking->calculatePrice();
    expect($lateEveningBooking->is_light_required)->toBeTrue();

    // Morning booking (before 18:00)
    $morningBooking = Booking::factory()->create([
        'court_id' => $this->court->id,
        'start_time' => '10:00',
    ]);
    $morningBooking->calculatePrice();
    expect($morningBooking->is_light_required)->toBeFalse();

    // Afternoon booking
    $afternoonBooking = Booking::factory()->create([
        'court_id' => $this->court->id,
        'start_time' => '17:00',
    ]);
    $afternoonBooking->calculatePrice();
    expect($afternoonBooking->is_light_required)->toBeFalse();
});

test('booking can be approved by admin', function () {
    $booking = Booking::factory()->create([
        'status' => 'pending',
        'approved_by' => null,
        'approved_at' => null,
    ]);

    $booking->update([
        'status' => 'confirmed',
        'approved_by' => $this->user->id,
        'approved_at' => now(),
    ]);

    expect($booking->status->value)->toBe('confirmed');
    expect($booking->approver->id)->toBe($this->user->id);
    expect($booking->approved_at)->not->toBeNull();
});

test('booking can be cancelled by admin', function () {
    $booking = Booking::factory()->create([
        'status' => 'confirmed',
        'cancelled_by' => null,
        'cancelled_at' => null,
    ]);

    $booking->update([
        'status' => 'cancelled',
        'cancelled_by' => $this->user->id,
        'cancelled_at' => now(),
    ]);

    expect($booking->status->value)->toBe('cancelled');
    expect($booking->canceller->id)->toBe($this->user->id);
    expect($booking->cancelled_at)->not->toBeNull();
});

test('booking can be edited by admin', function () {
    $booking = Booking::factory()->create([
        'notes' => 'Original notes',
        'edited_by' => null,
        'edited_at' => null,
    ]);

    $booking->update([
        'notes' => 'Updated notes',
        'edited_by' => $this->user->id,
        'edited_at' => now(),
    ]);

    expect($booking->notes)->toBe('Updated notes');
    expect($booking->editor->id)->toBe($this->user->id);
    expect($booking->edited_at)->not->toBeNull();
});

test('booking casts work correctly', function () {
    $booking = Booking::factory()->create([
        'date' => '2025-06-01',
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'price' => '100000',
        'light_surcharge' => '50000',
        'is_light_required' => '1',
        'status' => 'pending',
    ]);

    expect($booking->date)->toBeInstanceOf(Carbon::class);
    expect($booking->start_time)->toBeInstanceOf(Carbon::class);
    expect($booking->end_time)->toBeInstanceOf(Carbon::class);
    expect($booking->price)->toBe(100000);
    expect($booking->light_surcharge)->toBe(50000);
    expect($booking->is_light_required)->toBeTrue();
    expect($booking->status->value)->toBe('pending');
});

test('booking can handle zero hourly rate', function () {
    $court = Court::factory()->create([
        'hourly_rate' => 0,
        'light_surcharge' => 50000,
    ]);

    $booking = Booking::factory()->create([
        'court_id' => $court->id,
        'start_time' => '19:00',
    ]);

    $totalPrice = $booking->calculatePrice();
    expect($totalPrice)->toBe(50000); // Only light surcharge
    expect($booking->price)->toBe(0);
    expect($booking->light_surcharge)->toBe(50000);
});

test('booking can handle zero light surcharge', function () {
    $court = Court::factory()->create([
        'hourly_rate' => 100000,
        'light_surcharge' => 0,
    ]);

    $booking = Booking::factory()->create([
        'court_id' => $court->id,
        'start_time' => '19:00',
    ]);

    $totalPrice = $booking->calculatePrice();
    expect($totalPrice)->toBe(100000); // Only hourly rate
    expect($booking->price)->toBe(100000);
    expect($booking->light_surcharge)->toBe(0);
    expect($booking->is_light_required)->toBeTrue(); // Still required for evening
});

test('booking can be queried by status', function () {
    Booking::factory()->create(['status' => 'pending']);
    Booking::factory()->create(['status' => 'confirmed']);
    Booking::factory()->create(['status' => 'cancelled']);

    expect(Booking::where('status', 'pending')->count())->toBe(1);
    expect(Booking::where('status', 'confirmed')->count())->toBe(1);
    expect(Booking::where('status', 'cancelled')->count())->toBe(1);
});

test('booking can be queried by booking type', function () {
    Booking::factory()->create(['booking_type' => 'free']);
    Booking::factory()->create(['booking_type' => 'premium']);

    expect(Booking::where('booking_type', 'free')->count())->toBe(1);
    expect(Booking::where('booking_type', 'premium')->count())->toBe(1);
});
