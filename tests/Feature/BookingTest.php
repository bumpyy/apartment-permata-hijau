<?php

use App\Models\Court;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->court = Court::factory()->create(['name' => 'Court 23', 'light_surcharge' => 50000]);
    $this->tenant = Tenant::factory()->create();
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
});

test('tenant can select time slots', function () {
    Volt::test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->assertSet('selectedSlots', [])
        ->call('toggleTimeSlot', Carbon::tomorrow()->addDay()->format('Y-m-d').'-10:00')
        ->assertSet('selectedSlots', [Carbon::tomorrow()->addDay()->format('Y-m-d').'-10:00']);
});

test('tenant can process booking when authenticated', function () {
    Volt::actingAs($this->tenant, 'tenant')
        ->test('court-booking')
        ->set('courtNumber', $this->court->id)
        ->set('selectedSlots', [Carbon::tomorrow()->addDay()->format('Y-m-d').'-10:00'])
        ->call('prepareBookingData')
        ->call('processBooking')
        // ->dump('showThankYouModal')
        ->assertSet('showThankYouModal', true);

    $this->assertDatabaseHas('bookings', [
        'tenant_id' => $this->tenant->id,
        'court_id' => $this->court->id,
        'date' => Carbon::tomorrow()->addDay()->format('Y-m-d H:i:s'), // sqlite does not support date, it stores as datetime
        'start_time' => '10:00',
        'status' => 'pending',
    ]);
});
