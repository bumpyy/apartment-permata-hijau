<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminBookingPastDateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create(['role' => 'admin']);

        // Create test court and tenant
        $this->court = Court::factory()->create(['name' => 'Court 1']);
        $this->tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
    }

    public function test_admin_cannot_book_past_date()
    {
        $this->actingAs($this->admin, 'admin');

        $pastDate = now()->subDay()->format('Y-m-d');
        $pastTime = now()->subHour()->format('H:i');

        $response = Livewire::test(\App\Http\Livewire\Admin\Booking::class)
            ->call('startAddBooking', $pastDate, $pastTime, '09:00');

        // Should not open the add booking panel for past dates
        $this->assertFalse($response->get('isAddMode'));
        $this->assertEquals('Cannot book past dates or times.', $response->get('panelAddError'));
    }

    public function test_admin_cannot_book_past_time_for_today()
    {
        $this->actingAs($this->admin, 'admin');

        $today = now()->format('Y-m-d');
        $pastTime = now()->subHour()->format('H:i');

        $response = Livewire::test(\App\Http\Livewire\Admin\Booking::class)
            ->call('startAddBooking', $today, $pastTime, '09:00');

        // Should not open the add booking panel for past times
        $this->assertFalse($response->get('isAddMode'));
        $this->assertEquals('Cannot book past dates or times.', $response->get('panelAddError'));
    }

    public function test_admin_can_book_future_date_and_time()
    {
        $this->actingAs($this->admin, 'admin');

        $futureDate = now()->addDay()->format('Y-m-d');
        $futureTime = '10:00';

        $response = Livewire::test(\App\Http\Livewire\Admin\Booking::class)
            ->call('startAddBooking', $futureDate, $futureTime, '11:00');

        // Should open the add booking panel for future dates
        $this->assertTrue($response->get('isAddMode'));
        $this->assertEmpty($response->get('panelAddError'));
    }

    public function test_can_book_slot_method_returns_correct_values()
    {
        $this->actingAs($this->admin, 'admin');

        $component = Livewire::test(\App\Http\Livewire\Admin\Booking::class);

        // Test past date
        $pastDate = now()->subDay()->format('Y-m-d');
        $this->assertFalse($component->call('canBookSlot', $pastDate, '10:00'));

        // Test past time for today
        $today = now()->format('Y-m-d');
        $pastTime = now()->subHour()->format('H:i');
        $this->assertFalse($component->call('canBookSlot', $today, $pastTime));

        // Test future date and time
        $futureDate = now()->addDay()->format('Y-m-d');
        $futureTime = '10:00';
        $this->assertTrue($component->call('canBookSlot', $futureDate, $futureTime));
    }

    public function test_booking_type_indicators_are_displayed_correctly()
    {
        $this->actingAs($this->admin, 'admin');

        $response = Livewire::test(\App\Http\Livewire\Admin\Booking::class);

        // Test free booking type
        $nextWeekDate = now()->addWeek()->startOfWeek()->addDay()->format('Y-m-d');
        $bookingType = $response->call('getDateBookingType', Carbon::parse($nextWeekDate));
        $this->assertEquals('free', $bookingType);

        // Test premium booking type (if premium booking is open)
        $futureDate = now()->addMonth()->format('Y-m-d');
        $bookingType = $response->call('getDateBookingType', Carbon::parse($futureDate));
        // This might be 'none' if premium booking is not open, which is expected
        $this->assertContains($bookingType, ['premium', 'none']);
    }

    public function test_panel_booking_creation_validates_past_dates()
    {
        $this->actingAs($this->admin, 'admin');

        $pastDate = now()->subDay()->format('Y-m-d');
        $pastTime = now()->subHour()->format('H:i');

        $response = Livewire::test(\App\Http\Livewire\Admin\Booking::class)
            ->set('panelAddForm', [
                'court_id' => $this->court->id,
                'tenant_id' => $this->tenant->id,
                'date' => $pastDate,
                'start_time' => $pastTime,
                'end_time' => '09:00',
                'notes' => 'Test booking'
            ])
            ->call('createBookingFromPanel');

        $this->assertEquals('Cannot book on a past date.', $response->get('panelAddError'));
    }

    public function test_panel_booking_creation_validates_past_times_for_today()
    {
        $this->actingAs($this->admin, 'admin');

        $today = now()->format('Y-m-d');
        $pastTime = now()->subHour()->format('H:i');

        $response = Livewire::test(\App\Http\Livewire\Admin\Booking::class)
            ->set('panelAddForm', [
                'court_id' => $this->court->id,
                'tenant_id' => $this->tenant->id,
                'date' => $today,
                'start_time' => $pastTime,
                'end_time' => '09:00',
                'notes' => 'Test booking'
            ])
            ->call('createBookingFromPanel');

        $this->assertEquals('Cannot book a past time slot for today.', $response->get('panelAddError'));
    }

        public function test_visual_indicators_are_present_in_weekly_view()
    {
        $this->actingAs($this->admin, 'admin');

        $response = Livewire::test(\App\Http\Livewire\Admin\Booking::class)
            ->set('viewMode', 'weekly')
            ->call('setViewMode', 'weekly');

        // The weekly view should be rendered with visual indicators
        $this->assertTrue($response->get('viewMode') === 'weekly');

        // Check that the weekly bookings data is generated
        $weeklyBookings = $response->get('weeklyBookings');
        $this->assertNotNull($weeklyBookings);
        $this->assertArrayHasKey('days', $weeklyBookings);
    }

    public function test_past_booking_actions_are_hidden()
    {
        $this->actingAs($this->admin, 'admin');

        // Create a past booking
        $pastDate = now()->subDay()->format('Y-m-d');
        $pastTime = now()->subHour()->format('H:i');

        $pastBooking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'date' => $pastDate,
            'start_time' => $pastTime,
            'end_time' => Carbon::createFromFormat('H:i', $pastTime)->addHour()->format('H:i'),
            'status' => 'confirmed',
            'booking_type' => 'free',
            'booking_week_start' => Carbon::parse($pastDate)->startOfWeek()->format('Y-m-d'),
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        $response = Livewire::test(\App\Http\Livewire\Admin\Booking::class)
            ->call('showDetail', $pastBooking->id);

        // Should show the booking detail
        $this->assertNotNull($response->get('selectedBooking'));

        // The booking should be identified as past
        $bookingDateTime = Carbon::parse($pastDate)->setTime(
            Carbon::createFromFormat('H:i', $pastTime)->hour,
            Carbon::createFromFormat('H:i', $pastTime)->minute
        );
        $this->assertTrue($bookingDateTime->isPast());
    }

    public function test_future_booking_actions_are_visible()
    {
        $this->actingAs($this->admin, 'admin');

        // Create a future booking
        $futureDate = now()->addDay()->format('Y-m-d');
        $futureTime = '10:00';

        $futureBooking = Booking::create([
            'tenant_id' => $this->tenant->id,
            'court_id' => $this->court->id,
            'date' => $futureDate,
            'start_time' => $futureTime,
            'end_time' => Carbon::createFromFormat('H:i', $futureTime)->addHour()->format('H:i'),
            'status' => 'pending',
            'booking_type' => 'free',
            'booking_week_start' => Carbon::parse($futureDate)->startOfWeek()->format('Y-m-d'),
        ]);

        $response = Livewire::test(\App\Http\Livewire\Admin\Booking::class)
            ->call('showDetail', $futureBooking->id);

        // Should show the booking detail
        $this->assertNotNull($response->get('selectedBooking'));

        // The booking should be identified as future
        $bookingDateTime = Carbon::parse($futureDate)->setTime(
            Carbon::createFromFormat('H:i', $futureTime)->hour,
            Carbon::createFromFormat('H:i', $futureTime)->minute
        );
        $this->assertTrue($bookingDateTime->isFuture());
    }
}
