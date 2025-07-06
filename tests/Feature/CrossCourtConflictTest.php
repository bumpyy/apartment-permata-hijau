<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Settings\SiteSettings;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrossCourtConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test courts
        $this->court1 = Court::factory()->create(['name' => 'Court 1']);
        $this->court2 = Court::factory()->create(['name' => 'Court 2']);

        // Create test tenant
        $this->tenant = Tenant::factory()->create();

        // Enable cross-court conflict detection
        $settings = app(SiteSettings::class);
        $settings->enable_cross_court_conflict_detection = true;
        $settings->save();
    }

    public function test_cross_court_conflict_detection_is_enabled_by_default()
    {
        $settings = app(SiteSettings::class);
        expect($settings->isCrossCourtConflictDetectionEnabled())->toBeTrue();
    }

    public function test_admin_can_disable_cross_court_conflict_detection()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin, 'admin')
            ->test('admin.settings.site')
            ->set('enable_cross_court_conflict_detection', false)
            ->call('updateSiteSettings')
            ->assertSessionHas('status');

        $settings = app(SiteSettings::class);
        expect($settings->isCrossCourtConflictDetectionEnabled())->toBeFalse();
    }

    public function test_tenant_cannot_book_conflicting_times_on_different_courts()
    {
        $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();

        // Create a booking on Court 1
        $existingBooking = Booking::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court1->id,
            'date' => $bookingDate->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to book the same time on Court 2
        $slotKey = $bookingDate->format('Y-m-d').'-10:00';

        Livewire::actingAs($this->tenant, 'tenant')
            ->test('court-booking')
            ->set('courtNumber', $this->court2->id)
            ->set('selectedSlots', [$slotKey])
            ->call('toggleTimeSlot', $slotKey);

        // Should show cross-court conflict modal
        $this->assertTrue(Livewire::test('court-booking')->get('showCrossCourtConflictModal'));
    }

    public function test_tenant_can_book_different_times_on_different_courts()
    {
        $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();

        // Create a booking on Court 1 at 10:00
        $existingBooking = Booking::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court1->id,
            'date' => $bookingDate->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to book a different time on Court 2
        $slotKey = $bookingDate->format('Y-m-d').'-14:00';

        Livewire::actingAs($this->tenant, 'tenant')
            ->test('court-booking')
            ->set('courtNumber', $this->court2->id)
            ->set('selectedSlots', [$slotKey])
            ->call('toggleTimeSlot', $slotKey);

        // Should not show cross-court conflict modal
        $this->assertFalse(Livewire::test('court-booking')->get('showCrossCourtConflictModal'));
    }

    public function test_tenant_can_book_overlapping_times_when_conflict_detection_is_disabled()
    {
        // Disable cross-court conflict detection
        $settings = app(SiteSettings::class);
        $settings->enable_cross_court_conflict_detection = false;
        $settings->save();

        $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();

        // Create a booking on Court 1
        $existingBooking = Booking::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court1->id,
            'date' => $bookingDate->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
        ]);

        // Try to book the same time on Court 2
        $slotKey = $bookingDate->format('Y-m-d').'-10:00';

        Livewire::actingAs($this->tenant, 'tenant')
            ->test('court-booking')
            ->set('courtNumber', $this->court2->id)
            ->set('selectedSlots', [$slotKey])
            ->call('toggleTimeSlot', $slotKey);

        // Should not show cross-court conflict modal when disabled
        $this->assertFalse(Livewire::test('court-booking')->get('showCrossCourtConflictModal'));
    }

    public function test_cross_court_conflicts_are_detected_for_partial_overlaps()
    {
        $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();

        // Create a booking on Court 1 from 10:00 to 12:00
        $existingBooking = Booking::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court1->id,
            'date' => $bookingDate->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        // Try to book 11:00 to 12:00 on Court 2 (overlaps with existing booking)
        $slotKey = $bookingDate->format('Y-m-d').'-11:00';

        Livewire::actingAs($this->tenant, 'tenant')
            ->test('court-booking')
            ->set('courtNumber', $this->court2->id)
            ->set('selectedSlots', [$slotKey])
            ->call('toggleTimeSlot', $slotKey);

        // Should show cross-court conflict modal
        $this->assertTrue(Livewire::test('court-booking')->get('showCrossCourtConflictModal'));
    }

    public function test_cross_court_conflicts_include_correct_booking_details()
    {
        $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();

        // Create a booking on Court 1
        $existingBooking = Booking::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court1->id,
            'date' => $bookingDate->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'confirmed',
            'booking_reference' => 'TEST-REF-001',
        ]);

        // Try to book the same time on Court 2
        $slotKey = $bookingDate->format('Y-m-d').'-10:00';

        $component = Livewire::actingAs($this->tenant, 'tenant')
            ->test('court-booking')
            ->set('courtNumber', $this->court2->id)
            ->set('selectedSlots', [$slotKey])
            ->call('toggleTimeSlot', $slotKey);

        // Check that conflict details are populated correctly
        $conflictDetails = $component->get('crossCourtConflictDetails');
        expect($conflictDetails)->toHaveCount(1);
        expect($conflictDetails[0]['court_name'])->toBe('Court 1');
        expect($conflictDetails[0]['start_time'])->toBe('10:00');
        expect($conflictDetails[0]['end_time'])->toBe('11:00');
        expect($conflictDetails[0]['booking_reference'])->toBe('TEST-REF-001');
        expect($conflictDetails[0]['status'])->toBe('confirmed');
    }

    public function test_cross_court_conflicts_exclude_cancelled_bookings()
    {
        $bookingDate = Carbon::startOfWeek()->addWeek()->addDay();

        // Create a cancelled booking on Court 1
        $cancelledBooking = Booking::factory()->create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court1->id,
            'date' => $bookingDate->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => 'cancelled',
        ]);

        // Try to book the same time on Court 2
        $slotKey = $bookingDate->format('Y-m-d').'-10:00';

        Livewire::actingAs($this->tenant, 'tenant')
            ->test('court-booking')
            ->set('courtNumber', $this->court2->id)
            ->set('selectedSlots', [$slotKey])
            ->call('toggleTimeSlot', $slotKey);

        // Should not show cross-court conflict modal for cancelled bookings
        $this->assertFalse(Livewire::test('court-booking')->get('showCrossCourtConflictModal'));
    }
}
