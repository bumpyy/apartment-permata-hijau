<?php

namespace App\Livewire\Admin\Booking;

use Livewire\Component;

class StatsCards extends Component
{
    public string $searchTerm = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public function mount($searchTerm = '', $statusFilter = '', $typeFilter = '')
    {
        $this->searchTerm = $searchTerm;
        $this->statusFilter = $statusFilter;
        $this->typeFilter = $typeFilter;
    }

    public function render()
    {
        $stats = [
            ['title' => 'Total Bookings', 'value' => 128, 'color' => 'bg-blue-500'],
            ['title' => 'Pending', 'value' => 24, 'color' => 'bg-yellow-500'],
            ['title' => 'Confirmed', 'value' => 92, 'color' => 'bg-green-500'],
            ['title' => 'Cancelled', 'value' => 12, 'color' => 'bg-red-500'],
        ];

        return view('livewire.admin.booking.stats-cards', compact('stats'));
    }
}
