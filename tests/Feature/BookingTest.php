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
