<?php

namespace App\Livewire\Admin\Booking;

use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class Calendar extends Component
{
    public string $currentMonth;

    public string $currentYear;

    #[Reactive]
    public $bookings = [];

    public function mount()
    {
        $now = Carbon::now();
        $this->currentMonth = $now->format('m');
        $this->currentYear = $now->format('Y');

    }

    #[On('calendar-day-clicked')]
    public function showDayBookings($date)
    {
        // Store the clicked date, open a modal, fetch data, etc.
        logger('Calendar clicked: '.$date);
    }

    public function render()
    {

        return view('livewire.admin.booking.calendar');
    }
}
