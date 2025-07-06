<?php

namespace Tests\Feature;

use App\Enum\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_bookings_get_same_reference()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $court = Court::factory()->create();

        // Create multiple bookings for the same tenant and court
        $bookings = [];
        $reference = null;

        for ($i = 0; $i < 3; $i++) {
            $booking = Booking::create([
                'tenant_id' => $tenant->id,
                'court_id' => $court->id,
                'date' => Carbon::today()->addDays($i + 1),
                'start_time' => '10:00',
                'end_time' => '11:00',
                'status' => BookingStatusEnum::PENDING,
                'booking_type' => 'free',
                'booking_reference' => Booking::generateBookingReference($tenant->id, $court->id),
            ]);

            $bookings[] = $booking;

            if ($reference === null) {
                $reference = $booking->booking_reference;
            }
        }

        // All bookings should have the same reference
        foreach ($bookings as $booking) {
            $this->assertEquals($reference, $booking->booking_reference);
        }

        // The reference should follow the expected format
        $this->assertMatchesRegularExpression('/^BK\d+-\d+-\d{4}-\d{2}-\d{2}-[A-Z0-9]{4}$/', $reference);
    }

    public function test_different_tenants_get_different_references()
    {
        // Create test data
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $court = Court::factory()->create();

        // Create bookings for different tenants
        $booking1 = Booking::create([
            'tenant_id' => $tenant1->id,
            'court_id' => $court->id,
            'date' => Carbon::today()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => BookingStatusEnum::PENDING,
            'booking_type' => 'free',
            'booking_reference' => Booking::generateBookingReference($tenant1->id, $court->id),
        ]);

        $booking2 = Booking::create([
            'tenant_id' => $tenant2->id,
            'court_id' => $court->id,
            'date' => Carbon::today()->addDay(),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'status' => BookingStatusEnum::PENDING,
            'booking_type' => 'free',
            'booking_reference' => Booking::generateBookingReference($tenant2->id, $court->id),
        ]);

        // Different tenants should have different references
        $this->assertNotEquals($booking1->booking_reference, $booking2->booking_reference);
    }

    public function test_different_courts_get_different_references()
    {
        // Create test data
        $tenant = Tenant::factory()->create();
        $court1 = Court::factory()->create();
        $court2 = Court::factory()->create();

        // Create bookings for different courts
        $booking1 = Booking::create([
            'tenant_id' => $tenant->id,
            'court_id' => $court1->id,
            'date' => Carbon::today()->addDay(),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => BookingStatusEnum::PENDING,
            'booking_type' => 'free',
            'booking_reference' => Booking::generateBookingReference($tenant->id, $court1->id),
        ]);

        $booking2 = Booking::create([
            'tenant_id' => $tenant->id,
            'court_id' => $court2->id,
            'date' => Carbon::today()->addDay(),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'status' => BookingStatusEnum::PENDING,
            'booking_type' => 'free',
            'booking_reference' => Booking::generateBookingReference($tenant->id, $court2->id),
        ]);

        // Different courts should have different references
        $this->assertNotEquals($booking1->booking_reference, $booking2->booking_reference);
    }

    public function test_reference_format_is_correct()
    {
        $tenant = Tenant::factory()->create();
        $court = Court::factory()->create();

        $reference = Booking::generateBookingReference($tenant->id, $court->id);

        // Check format: BK{tenant_id}-{court_id}-{date}-{random}
        $this->assertMatchesRegularExpression('/^BK\d+-\d+-\d{4}-\d{2}-\d{2}-[A-Z0-9]{4}$/', $reference);

        // Parse the reference to verify components
        $parts = explode('-', $reference);
        $this->assertEquals('BK', $parts[0]);
        $this->assertEquals($tenant->id, (int) $parts[1]);
        $this->assertEquals($court->id, (int) $parts[2]);
        $this->assertEquals(Carbon::today()->format('Y-m-d'), $parts[3] . '-' . $parts[4] . '-' . $parts[5]);
        $this->assertEquals(4, strlen($parts[6])); // Random part should be 4 characters
    }
}
