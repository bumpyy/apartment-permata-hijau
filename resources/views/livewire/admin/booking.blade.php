<?php
use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('components.backend.layouts.app')] class extends Component
{
    public $searchTerm = '';

    public $statusFilter = '';

    public $typeFilter = '';

    public $selectedDate = '';

    public $bookings = [];

    public function mount()
    {
        $this->selectedDate = today()->format('Y-m-d');
        $this->updateBookings();
    }

    #[On('calendar-day-clicked')]
    public function updateSelectedDate($date)
    {
        $this->selectedDate = $date;
        $this->updateBookings();
    }

    #[On('filter-bar-updated')]
    public function updateFilters($searchTerm, $statusFilter, $typeFilter)
    {
        $this->searchTerm = $searchTerm;
        $this->statusFilter = $statusFilter;
        $this->typeFilter = $typeFilter;
        $this->updateBookings();
    }

    public function updateBookings()
    {
        $this->bookings = Booking::when($this->searchTerm, function ($query, $term) {
            return $query
                ->where('court_id', 'like', "%{$term}%")
                ->orWhere('booking_type', 'like', "%{$term}%")
                ->orWhere('status', 'like', "%{$term}%");
        })
            ->when($this->statusFilter, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($this->typeFilter, function ($query, $type) {
                return $query->where('booking_type', $type);
            })
            ->with(['court', 'tenant'])
            ->where('date', $this->selectedDate)
            ->get();
    }
};
?>

<div class="min-h-screen">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- {/* Header */} -->
        <div class="mb-8">
            <div class="mb-2 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600">
                    <flux:icon.building-office-2 class="h-6 w-6 text-white" />
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Tenant Booking Admin</h1>
                    <p class="text-gray-600">Manage property viewings, maintenance, and inspections</p>
                </div>
            </div>
        </div>

        <!-- {/* Stats Cards */} -->
        <livewire:admin.booking.stats-cards :search-term="$searchTerm" :status-filter="$statusFilter" :type-filter="$typeFilter" />

        <!-- {/* Filter Bar */} -->
        <livewire:admin.booking.filter-bar wire:model="searchTerm" wire:model="statusFilter" wire:model="typeFilter" />

        <!-- {/* Main Content */} -->
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <livewire:admin.booking.calendar :search-term="$searchTerm" :status-filter="$statusFilter" :type-filter="$typeFilter" />

            <livewire:admin.booking.booking-list :search-term="$searchTerm" :status-filter="$statusFilter" :type-filter="$typeFilter" :selected-date="$selectedDate"
                :bookings="$bookings" />
        </div>
    </div>
</div>
