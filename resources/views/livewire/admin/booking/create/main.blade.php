<?php

use App\Models\Booking;
use App\Models\PremiumDateOverride;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('components.backend.layouts.app')] class extends Component
{
    public $searchTerm = '';

    public $statusFilter = '';

    public $typeFilter = '';

    public $courtFilter = '';

    public $selectedDate = '';

    public $bookings;

    public $overrideDates = [];

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
    public function updateFilters($searchTerm, $statusFilter, $typeFilter, $courtFilter)
    {
        $this->searchTerm = $searchTerm;
        $this->statusFilter = $statusFilter;
        $this->typeFilter = $typeFilter;
        $this->courtFilter = $courtFilter;
        $this->updateBookings();
    }

    public function updateBookings()
    {
        $this->bookings = Booking::when($this->searchTerm, function ($query, $term) {
            return $query->whereRelation('tenant', 'name', 'like', "%{$term}%");
        })
            ->when($this->statusFilter, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($this->typeFilter, function ($query, $type) {
                return $query->where('booking_type', $type);
            })
            ->when($this->courtFilter, function ($query, $court) {
                return $query->where('court_id', $court);
            })
            ->where('date', $this->selectedDate)
            ->with(['court', 'tenant', 'tenant.media'])
            ->get();
    }

    public function cancelBooking($bookingId)
    {
        $booking = Booking::find($bookingId);
        $booking->update(['status' => 'cancelled']);
        $this->updateBookings();

        session()->flash('message', 'Booking cancelled successfully!');
    }

    public function refreshOverrides(): void
    {
        $overrides = PremiumDateOverride::orderBy('date')->get();
        $this->overrides = $overrides->groupBy(fn ($item) => \Carbon\Carbon::parse($item->date)->format('Y F'))->toArray();
        $this->overrideDates = $overrides->pluck('date')->values()->toArray();
    }
};
?>

<div class="min-h-screen">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- {/* Header */} -->
        <div class="mb-8">
            <div class="mb-4">
                <a href="{{ route('admin.booking.list') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back to Bookings
                </a>
            </div>
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

        <!-- {/* Filter Bar */} -->
        <livewire:admin.booking.create.filter-bar :$searchTerm :$statusFilter :$typeFilter :$courtFilter />

        <!-- {/* Main Content */} -->
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
            <livewire:admin.booking.create.calendar />

            <livewire:admin.booking.create.booking-list :$selectedDate :$bookings />
        </div>
    </div>
</div>
