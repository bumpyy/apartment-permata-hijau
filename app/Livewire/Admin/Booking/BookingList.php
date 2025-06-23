<?php

namespace App\Livewire\Admin\Booking;

use Livewire\Attributes\Reactive;
use Livewire\Component;

class BookingList extends Component
{
    public string $searchTerm = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    #[Reactive]
    public $selectedDate = '';

    #[Reactive]
    public $bookings = [];

    public function mount(
        $searchTerm = '',
        $statusFilter = '',
        $typeFilter = '',
        $selectedDate = '',
        $bookings = []
    ) {
        $this->searchTerm = $searchTerm;
        $this->statusFilter = $statusFilter;
        $this->typeFilter = $typeFilter;
        $this->selectedDate = $selectedDate;
        $this->bookings = $bookings;
    }

    public function render()
    {
        return view('livewire.admin.booking.booking-list', [
            'searchTerm' => $this->searchTerm,
            'statusFilter' => $this->statusFilter,
            'typeFilter' => $this->typeFilter,
            'selectedDate' => $this->selectedDate,
            'bookings' => $this->bookings,
        ]);
    }
}
