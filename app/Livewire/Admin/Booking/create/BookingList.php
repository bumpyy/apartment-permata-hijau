<?php

namespace App\Livewire\Admin\Booking\create;

use Carbon\Carbon;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class BookingList extends Component
{
    #[Reactive]
    public $selectedDate;

    #[Reactive]
    public $bookings;

    public $slotDisplay = 'selected';

    public $timeSlots = [];

    public function mount()
    {
        $this->generateTimeSlots();
    }

    /**
     * Generate the standard time slots (8am-10pm, 1-hour intervals)
     * Used by all views to show available booking times
     */
    public function generateTimeSlots()
    {
        $this->timeSlots = collect(range(8, 22))
            ->map(function ($hour) {
                $start = Carbon::parse($hour.':00');
                $end = $start->copy()->addHour();

                return (object) [
                    'start_time' => $start,
                    'end_time' => $end,
                    'is_peak' => $start->hour >= 18, // After 6pm = peak hours (lights required)
                ];
            });
    }

    public function changeSlotDisplay($slot)
    {
        if ($slot !== $this->slotDisplay) {
            $this->slotDisplay = in_array($slot, ['selected', 'all']) ? $slot : 'selected';
        }
    }

    public function render()
    {
        $sortedBookings = clone $this->bookings->map(fn ($b) => clone $b)->sortBy('start_time');

        return view('livewire.admin.booking.create.partials.booking-list', compact('sortedBookings'));
    }
}
