<?php

use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.frontend.layouts.app')] class extends Component
{
    public $bookings = [];

    public function mount()
    {
        $this->loadBookings();
    }

    public function loadBookings()
    {
        $this->bookings = Booking::with(['tenant', 'court'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('status');
    }

    public function confirmBooking($bookingId)
    {
        $booking = Booking::find($bookingId);
        $booking->update([
            'status' => 'confirmed',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->loadBookings();
        session()->flash('message', 'Booking confirmed successfully!');
    }

    public function denyBooking($bookingId)
    {
        $booking = Booking::find($bookingId);
        $booking->update([
            'status' => 'cancelled',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $this->loadBookings();
        session()->flash('message', 'Booking denied successfully!');
    }
};

?>

<div class="max-w-7xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">ADMIN - CREATE BOOKING</h1>

    @if (session()->has('message'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
        {{ session('message') }}
    </div>
    @endif

    <!-- Confirmed Bookings -->
    @if(isset($bookings['confirmed']) && $bookings['confirmed']->count() > 0)
    <div class="mb-6">
        <h2 class="text-lg font-semibold mb-3">Confirmed Bookings</h2>
        @foreach($bookings['confirmed'] as $booking)
        <div class="bg-gray-200 p-4 rounded-lg mb-2 flex justify-between items-center">
            <div>
                <span class="font-semibold">{{ $booking->tenant->display_name }}</span> booked:
                <br>
                <span class="text-sm">
                    Court {{ $booking->court->name }} / {{ $booking->date->format('d M Y') }} /
                    {{ $booking->start_time->format('H:i') }}-{{ $booking->end_time->format('H:i') }}
                </span>
            </div>
            <div class="text-green-600">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Pending Bookings -->
    @if(isset($bookings['pending']) && $bookings['pending']->count() > 0)
    <div class="mb-6">
        <h2 class="text-lg font-semibold mb-3">Pending Bookings</h2>
        @foreach($bookings['pending'] as $booking)
        <div class="bg-gray-200 p-4 rounded-lg mb-2 flex justify-between items-center">
            <div>
                <span class="font-semibold">{{ $booking->tenant->display_name }}</span> request booking:
                <br>
                <span class="text-sm">
                    Court {{ $booking->court->name }} / {{ $booking->date->format('d M Y') }} /
                    {{ $booking->start_time->format('H:i') }}-{{ $booking->end_time->format('H:i') }}
                </span>
                @if($booking->is_light_required)
                <br><span class="text-xs text-orange-600">(additional 50k for tennis court light applied)</span>
                @endif
            </div>
            <div class="flex gap-2">
                <button
                    class="bg-gray-500 text-white px-4 py-2 rounded text-sm hover:bg-gray-600 transition-colors"
                    wire:click="denyBooking({{ $booking->id }})">
                    DENY
                </button>
                <button
                    class="bg-gray-700 text-white px-4 py-2 rounded text-sm hover:bg-gray-800 transition-colors"
                    wire:click="confirmBooking({{ $booking->id }})">
                    CONFIRM PAYMENT
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @if((!isset($bookings['confirmed']) || $bookings['confirmed']->count() === 0) &&
    (!isset($bookings['pending']) || $bookings['pending']->count() === 0))
    <div class="text-center py-8 text-gray-500">
        No bookings found.
    </div>
    @endif
</div>
