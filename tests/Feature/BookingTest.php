<?php

use App\Models\Tenant;
use App\Models\Booking;
use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->court = Court::factory()->create(['name' => 'Court 2', 'light_surcharge' => 50000]);
    $this->tenant = Tenant::factory()->create(['tenant_id' => 'tenant#164', 'booking_limit' => 5]);
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
});

test('tenant can select time slots', function () {
    Volt::test('pages.court-booking')
        ->set('courtNumber', 2)
        ->call('mount')
        ->assertSet('selectedSlots', [])
        ->call('toggleTimeSlot', '2025-06-01-10:00')
        ->assertSet('selectedSlots', ['2025-06-01-10:00']);
});

test('tenant can process booking when authenticated', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('pages.court-booking')
        ->set('courtNumber', 2)
        ->set('selectedSlots', ['2025-06-01-10:00'])
        ->call('prepareBookingData')
        ->call('processBooking')
        ->assertSet('showThankYouModal', true);

    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $this->tenant->id,
        'court_id' => 2,
        'date' => '2025-06-01',
        'start_time' => '10:00',
        'status' => 'pending',
    ]);
});
