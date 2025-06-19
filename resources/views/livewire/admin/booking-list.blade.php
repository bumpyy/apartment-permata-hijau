<?php
// TODO: OPTIMIZE THESE QUERIES

use App\Models\Booking;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.backend.layouts.app')] class extends Component
{
    public $upcomingBookings = [];

    public $pastBookings = [];

    public $activeTab = 'upcoming';

    public function mount()
    {
        $this->loadBookings();
    }

    public function loadBookings()
    {
        $this->upcomingBookings = Booking::where('date', '>=', Carbon::today()->format('Y-m-d'))
            ->where('status', '!=', 'cancelled')
            ->with('court')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $this->pastBookings = Booking::where('date', '<', Carbon::today()->format('Y-m-d'))
            ->with('court')
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->limit(20)
            ->get();

    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function cancelBooking($bookingId)
    {
        $booking = Booking::find($bookingId);
        $booking->update(['status' => 'cancelled']);
        $this->loadBookings();
        session()->flash('message', 'Booking cancelled successfully!');
    }
}
?>

<section>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">

        <div class="container py-8">

            @if (session()->has('message'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
                {{ session('message') }}
            </div>
            @endif

            <!-- Bookings Section -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <button
                            wire:click="setActiveTab('upcoming')"
                            class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200
                                @if($activeTab === 'upcoming')
                                    border-blue-500 text-blue-600
                                @else
                                    border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300
                                @endif">
                            📅 Upcoming Bookings ({{ count($upcomingBookings) }})
                        </button>
                        <button
                            wire:click="setActiveTab('past')"
                            class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200
                                @if($activeTab === 'past')
                                    border-blue-500 text-blue-600
                                @else
                                    border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300
                                @endif">
                            📚 Booking History ({{ count($pastBookings) }})
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    @if($activeTab === 'upcoming')
                    @if(count($upcomingBookings) > 0)
                    <div class="space-y-4">
                        @foreach($upcomingBookings as $booking)
                        <div class="booking-card bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-lg p-6 hover:shadow-md transition-all duration-300">
                            <div class="flex items-center flex-wrap gap-2 justify-between">
                                <div class="flex flex-wrap items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="py-2 px-3 bg-gradient-to-br from-blue-500 to-purple-600 rounded-md flex items-center justify-center text-white font-bold">
                                            {{ $booking->court->name }}
                                        </div>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            Court {{ $booking->court->name }}
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium sm:ml-2
                                                            @if($booking->booking_type === 'free') bg-blue-100 text-blue-800 @else bg-purple-100 text-purple-800 @endif">
                                                @if($booking->booking_type === 'free') 🆓 Free @else ⭐ Premium @endif
                                            </span>
                                        </h3>
                                        <p class="text-sm text-gray-600">
                                            📅 {{ $booking->date->format('l, F j, Y') }} •
                                            🕐 {{ $booking->start_time->format('g:i A') }} - {{ $booking->end_time->format('g:i A') }}
                                        </p>
                                        @if($booking->is_light_required)
                                        <p class="text-xs text-orange-600 mt-1">💡 Court lights included (+IDR 50k)</p>
                                        @endif
                                        @if($booking->booking_reference)
                                        <p class="text-xs text-gray-500 mt-1">Reference: #{{ $booking->booking_reference }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center flex-wrap gap-3">
                                    <span
                                        @class([ "inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                                        , 'bg-green-100 text-green-800'=> $booking->status === 'confirmed',
                                        'bg-orange-100 text-orange-800' => $booking->status === 'pending',
                                        'bg-red-100 text-red-800' => $booking->status === 'cancelled',
                                        ])
                                        >
                                        @if($booking->status === 'confirmed') ✅ Confirmed
                                        @elseif($booking->status === 'pending') ⏳ Pending
                                        @else ❌ Cancelled @endif
                                    </span>
                                    @if($booking->status !== 'cancelled' && $booking->date->gt(Carbon::today()))
                                    <button
                                        wire:click="cancelBooking({{ $booking->id }})"
                                        onclick="return confirm('Are you sure you want to cancel this booking?')"
                                        class="px-3 py-1 bg-red-100  text-red-700 rounded-lg hover:bg-red-200 transition-colors text-sm">
                                        ❌ Cancel
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">🎾</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No upcoming bookings</h3>
                        <p class="text-gray-600 mb-6">Ready to book your next tennis session?</p>
                        <a href="{{ route('facilities') }}"
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-300 transform hover:scale-105">
                            🎾 Book a Court
                        </a>
                    </div>
                    @endif
                    @else
                    @if(count($pastBookings) > 0)
                    <div class="space-y-4">
                        @foreach($pastBookings as $booking)
                        <div class="booking-card bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-lg p-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 bg-gradient-to-br from-gray-400 to-gray-500 rounded-full flex items-center justify-center text-white font-bold">
                                            {{ $booking->court->name }}
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-700">
                                            Court {{ $booking->court->name }}
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ml-2
                                                            @if($booking->booking_type === 'free') bg-blue-100 text-blue-800 @else bg-purple-100 text-purple-800 @endif">
                                                @if($booking->booking_type === 'free') 🆓 Free @else ⭐ Premium @endif
                                            </span>
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            📅 {{ $booking->date->format('l, F j, Y') }} •
                                            🕐 {{ $booking->start_time->format('g:i A') }} - {{ $booking->end_time->format('g:i A') }}
                                        </p>
                                        @if($booking->is_light_required)
                                        <p class="text-xs text-orange-500 mt-1">💡 Court lights included (+IDR 50k)</p>
                                        @endif
                                        @if($booking->booking_reference)
                                        <p class="text-xs text-gray-400 mt-1">Reference: #{{ $booking->booking_reference }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                                    @if($booking->status === 'confirmed') bg-green-100 text-green-700
                                                    @elseif($booking->status === 'pending') bg-orange-100 text-orange-700
                                                    @else bg-red-100 text-red-700 @endif">
                                        @if($booking->status === 'confirmed') ✅ Completed
                                        @elseif($booking->status === 'pending') ⏳ Was Pending
                                        @else ❌ Cancelled @endif
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">📚</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No booking history</h3>
                        <p class="text-gray-600">Your completed bookings will appear here.</p>
                    </div>
                    @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
