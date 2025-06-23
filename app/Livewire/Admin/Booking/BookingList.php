<?php

namespace App\Livewire\Admin\Booking;

use Livewire\Attributes\Reactive;
use Livewire\Component;

class BookingList extends Component
{
    #[Reactive]
    public $selectedDate;

    #[Reactive]
    public $bookings;

    public function render()
    {
        return view('livewire.admin.booking.booking-list');
    }
}
