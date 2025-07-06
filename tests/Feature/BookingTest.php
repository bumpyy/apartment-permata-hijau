<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->court = Court::factory()->create([
        'name' => 'Court 23',
        'light_surcharge' => 50000,
        'hourly_rate' => 100000,
    ]);
    $this->tenant = Tenant::factory()->create([
        'booking_limit' => 3,
    ]);
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
});

test('tenant cannot select past time slots', function () {
    $pastDate = Carbon::yesterday()->format('Y-m-d');

    Volt::test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->assertSet('selectedSlots', [])
        ->call('toggleTimeSlot', $pastDate.'-10:00')
        ->assertSet('selectedSlots', []);
});

test('tenant cannot select current week time slots', function () {
    $currentWeekSlot = Carbon::startOfWeek()->addDay()->format('Y-m-d').'-10:00';

    Volt::test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->assertSet('selectedSlots', [])
        ->call('toggleTimeSlot', $currentWeekSlot)
        ->assertSet('selectedSlots', []);
});

test('tenant can select next week time slots', function () {
    $nextWeekSlot = Carbon::startOfWeek()->addWeek()->addDay()->format('Y-m-d').'-10:00';

    Volt::test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->assertSet('selectedSlots', [])
        ->call('jumpToWeek', 1)
        ->call('toggleTimeSlot', $nextWeekSlot)
        ->assertSet('selectedSlots', [$nextWeekSlot]);
});

test('tenant can process booking when authenticated', function () {
    $nextWeekSlot = Carbon::startOfWeek()->addWeek()->addDay()->format('Y-m-d').'-10:00';

    Volt::actingAs($this->tenant, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$nextWeekSlot])
        ->call('prepareBookingData')
        ->call('processBooking')
        ->assertSet('showThankYouModal', true);

    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::startOfWeek()->addWeek()->addDay()->format('Y-m-d'),
        'start_time' => '10:00',
        'status' => 'pending',
    ]);
});

test('tenant cannot book more than their limit', function () {
    // Create bookings up to the limit
    Booking::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::startOfWeek()->addWeek()->addDay(),
        'status' => 'confirmed',
    ]);

    $nextWeekSlot = Carbon::startOfWeek()->addWeek()->addDay()->format('Y-m-d').'-10:00';

    Volt::actingAs($this->tenant, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$nextWeekSlot])
        ->call('prepareBookingData')
        ->assertSet('quotaWarning', 'You have reached your booking limit for this week.');
});

test('tenant can view their booking quota', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->call('getQuotaInfo')
        ->assertSet('quotaInfo.remaining', 3);
});

test('tenant cannot book overlapping time slots', function () {
    // Create an existing booking
    Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::startOfWeek()->addWeek()->addDay(),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'status' => 'confirmed',
    ]);

    $overlappingSlot = Carbon::startOfWeek()->addWeek()->addDay()->format('Y-m-d').'-10:30';

    Volt::actingAs($this->tenant, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$overlappingSlot])
        ->call('prepareBookingData')
        ->assertSet('quotaWarning', 'Selected time slot conflicts with existing booking.');
});

test('tenant can cancel their own booking', function () {
    $booking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('cancelBooking', $booking->id);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'cancelled',
    ]);
});

test('tenant cannot cancel other tenants booking', function () {
    $otherTenant = Tenant::factory()->create();
    $booking = Booking::factory()->create([
        'tenant_id' => $otherTenant->id,
        'court_id' => $this->court->id,
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->call('cancelBooking', $booking->id);

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'status' => 'confirmed', // Should remain unchanged
    ]);
});

test('tenant can view their upcoming bookings', function () {
    $upcomingBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->assertSee($upcomingBooking->court->name)
        ->assertSee($upcomingBooking->date->format('d M Y'));
});

test('tenant can view their past bookings', function () {
    $pastBooking = Booking::factory()->create([
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::yesterday(),
        'status' => 'confirmed',
    ]);

    Volt::actingAs($this->tenant, 'tenant')
        ->test('tenant.dashboard')
        ->set('activeTab', 'past')
        ->assertSee($pastBooking->court->name)
        ->assertSee($pastBooking->date->format('d M Y'));
});

test('evening bookings automatically include light surcharge', function () {
    $eveningSlot = Carbon::startOfWeek()->addWeek()->addDay()->format('Y-m-d').'-19:00';

    Volt::actingAs($this->tenant, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$eveningSlot])
        ->call('prepareBookingData')
        ->call('processBooking');

    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'start_time' => '19:00',
        'is_light_required' => true,
        'light_surcharge' => 50000,
    ]);
});

test('morning bookings do not include light surcharge', function () {
    $morningSlot = Carbon::startOfWeek()->addWeek()->addDay()->format('Y-m-d').'-10:00';

    Volt::actingAs($this->tenant, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$morningSlot])
        ->call('prepareBookingData')
        ->call('processBooking');

    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'start_time' => '10:00',
        'is_light_required' => false,
        'light_surcharge' => 0,
    ]);
});

test('tenant cannot access booking page without authentication', function () {
    $response = $this->get(route('facilities.tennis.court.booking', ['id' => $this->court->id]));
    $response->assertRedirect('/login');
});

test('tenant can view court availability', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->assertSee('Court 23')
        ->assertSee('Available Time Slots');
});

test('booking reference is generated correctly', function () {
    $nextWeekSlot = Carbon::startOfWeek()->addWeek()->addDay()->format('Y-m-d').'-10:00';

    Volt::actingAs($this->tenant, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$nextWeekSlot])
        ->call('prepareBookingData')
        ->call('processBooking');

    $booking = Booking::where('tenant_id', $this->tenant->id)->first();
    expect($booking->booking_reference)->toMatch('/^A\d{4}$/');
});

test('duplicate booking prevention works across multiple tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();
    $slotKey = $bookingDate->format('Y-m-d').'-10:00';

    // First tenant books a slot
    Volt::actingAs($tenant1, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$slotKey])
        ->call('confirmBooking')
        ->call('processBooking');

    // Verify the booking was created
    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $tenant1->id,
        'court_id' => $this->court->id,
        'date' => $bookingDate->format('Y-m-d'),
        'start_time' => '10:00:00',
        'status' => 'pending',
    ]);

    // Second tenant tries to book the same slot
    Volt::actingAs($tenant2, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$slotKey])
        ->call('toggleTimeSlot', $slotKey);

    // The slot should be marked as unavailable
    $this->assertTrue(Booking::isSlotBooked($this->court->id, $bookingDate->format('Y-m-d'), '10:00'));
});

test('booking conflict detection removes conflicting slots from selection', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();
    $slotKey1 = $bookingDate->format('Y-m-d').'-10:00';
    $slotKey2 = $bookingDate->format('Y-m-d').'-11:00';

    // First tenant books a slot
    Volt::actingAs($tenant1, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$slotKey1])
        ->call('confirmBooking')
        ->call('processBooking');

    // Second tenant selects both slots (one is now taken)
    Volt::actingAs($tenant2, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$slotKey1, $slotKey2])
        ->call('validateSlotsStillAvailable');

    // The conflicting slot should be removed from selection
    $this->assertNotContains($slotKey1, $this->selectedSlots);
    $this->assertContains($slotKey2, $this->selectedSlots);
});

test('conflict modal is shown when slots become unavailable', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();
    $slotKey = $bookingDate->format('Y-m-d').'-10:00';

    // First tenant books a slot
    Volt::actingAs($tenant1, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$slotKey])
        ->call('confirmBooking')
        ->call('processBooking');

    // Second tenant tries to book the same slot
    Volt::actingAs($tenant2, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$slotKey])
        ->call('validateSlotsStillAvailable')
        ->assertSet('showConflictModal', true)
        ->assertSet('conflictDetails', function ($conflicts) use ($slotKey) {
            return count($conflicts) === 1 && $conflicts[0]['slot_key'] === $slotKey;
        });
});

test('real-time conflict prevention shows appropriate notifications', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();
    $slotKey = $bookingDate->format('Y-m-d').'-10:00';

    // First tenant books a slot
    Volt::actingAs($tenant1, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [$slotKey])
        ->call('confirmBooking')
        ->call('processBooking');

    // Second tenant tries to select the same slot
    Volt::actingAs($tenant2, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->call('toggleTimeSlot', $slotKey)
        ->assertSet('quotaWarning', '⏰ This time slot was just booked by another tenant. Please select a different time.');
});
