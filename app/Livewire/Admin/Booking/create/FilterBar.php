<?php

namespace App\Livewire\Admin\Booking\create;

use Livewire\Attributes\Url;
use Livewire\Component;

class FilterBar extends Component
{
    #[Url(except: '', as: 'q')]
    public $searchTerm = '';

    #[Url(except: '', as: 'status')]
    public string $statusFilter = '';

    #[Url(except: '', as: 'type')]
    public string $typeFilter = '';

    #[Url(except: '', as: 'court')]
    public string $courtFilter = '';

    public function mount()
    {
        $this->dispatchFilterUpdate();
    }

    public function updated()
    {
        $this->dispatchFilterUpdate();
    }

    private function dispatchFilterUpdate()
    {
        $this->dispatch('filter-bar-updated', $this->searchTerm, $this->statusFilter, $this->typeFilter, $this->courtFilter);
    }

    public function render()
    {
        return view('livewire.admin.booking.create.partials.filter-bar');
    }
}
