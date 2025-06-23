<?php

namespace App\Livewire\Admin\Booking;

use Livewire\Attributes\Url;
use Livewire\Component;

class FilterBar extends Component
{
    #[Url(except: '')]
    public $searchTerm = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    public function mount()
    {
        $this->dispatch('filter-bar-updated', $this->searchTerm, $this->statusFilter, $this->typeFilter);
    }

    public function updated($property)
    {
        $this->dispatch('filter-bar-updated', $this->searchTerm, $this->statusFilter, $this->typeFilter);
    }

    public function render()
    {
        return view('livewire.admin.booking.filter-bar');
    }
}
