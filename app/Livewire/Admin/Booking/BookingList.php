<?php

namespace App\Livewire\Admin\Booking;

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
        $this->timeSlots = [];
        $start = Carbon::parse('08:00');
        $end = Carbon::parse('22:00');

        while ($start < $end) {
            $this->timeSlots[] = [
                'start' => $start->format('H:i'),
                'end' => $start->copy()->addHour()->format('H:i'),
                'is_peak' => $start->hour >= 18, // After 6pm = peak hours (lights required)
            ];
            $start->addHour();
        }
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

        return view('livewire.admin.booking.booking-list', compact('sortedBookings'));
    }
}
