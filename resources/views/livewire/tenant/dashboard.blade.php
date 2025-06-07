<?php
// TODO: OPTIMIZE THESE QUERIES
use function Livewire\Volt\{layout, state, mount};
use App\Models\Booking;
use Carbon\Carbon;

layout('components.frontend.app');

state([
    'tenant' => null,
    'upcomingBookings' => [],
    'pastBookings' => [],
    'quotaInfo' => [],
    'stats' => [],
    'activeTab' => 'upcoming',
]);

mount(function () {
    $this->tenant = auth('tenant')->user();
    $this->loadBookings();
    $this->loadQuotaInfo();
    $this->loadStats();
});

$loadBookings = function () {
    $this->upcomingBookings = $this->tenant->bookings()
        ->where('date', '>=', Carbon::today())
        ->where('status', '!=', 'cancelled')
        ->with('court')
        ->orderBy('date')
        ->orderBy('start_time')
        ->get();

    $this->pastBookings = $this->tenant->bookings()
        ->where('date', '<', Carbon::today())
        ->with('court')
        ->orderBy('date', 'desc')
        ->orderBy('start_time', 'desc')
        ->limit(20)
        ->get();
};

$loadQuotaInfo = function () {
    $this->quotaInfo = [
        'free' => $this->tenant->free_booking_quota,
        'premium' => $this->tenant->premium_booking_quota,
        'weekly_remaining' => $this->tenant->remaining_weekly_quota
    ];
};

$loadStats = function () {
    $this->stats = [
        'total_bookings' => $this->tenant->bookings()->count(),
        'this_month' => $this->tenant->bookings()
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->count(),
        'confirmed' => $this->tenant->bookings()
            ->where('status', 'confirmed')
            ->where('date', '>=', Carbon::today())
            ->count(),
        'pending' => $this->tenant->bookings()
            ->where('status', 'pending')
            ->where('date', '>=', Carbon::today())
            ->count(),
    ];
};

$setActiveTab = function ($tab) {
    $this->activeTab = $tab;
};

$cancelBooking = function ($bookingId) {
    $booking = Booking::find($bookingId);
    if ($booking && $booking->tenant_id === $this->tenant->id) {
        $booking->update(['status' => 'cancelled']);
        $this->loadBookings();
        $this->loadQuotaInfo();
        $this->loadStats();
        session()->flash('message', 'Booking cancelled successfully!');
    }
};

?>

<section>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-700 text-white py-12">
            <div class="max-w-7xl mx-auto px-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-4xl font-bold mb-2">🎾 Welcome back, {{ $tenant->name }}!</h1>
                        <p class="text-blue-100 text-lg">{{ $tenant->display_name }} • Manage your tennis court bookings</p>
                    </div>
                    <!-- <div class="text-right">
                        <div class="text-sm text-blue-200">{{ Carbon::now()->format('l, F j, Y') }}</div>
                        <div class="text-lg font-semibold">{{ Carbon::now()->format('g:i A') }}</div>
                    </div> -->
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-6 py-8">
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="stat-card bg-white p-6 rounded-xl shadow-sm border hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Bookings</p>
                            <p class="text-3xl font-bold text-gray-900">{{ $stats['total_bookings'] }}</p>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white p-6 rounded-xl shadow-sm border hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">This Month</p>
                            <p class="text-3xl font-bold text-gray-900">{{ $stats['this_month'] }}</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white p-6 rounded-xl shadow-sm border hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Confirmed</p>
                            <p class="text-3xl font-bold text-green-600">{{ $stats['confirmed'] }}</p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="stat-card bg-white p-6 rounded-xl shadow-sm border hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Pending</p>
                            <p class="text-3xl font-bold text-orange-600">{{ $stats['pending'] }}</p>
                        </div>
                        <div class="p-3 bg-orange-100 rounded-full">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quota Overview -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="quota-card bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-bold text-blue-800">🆓 Free Booking Quota</h4>
                        <div class="text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded-full">Weekly</div>
                    </div>
                    <p class="text-4xl font-bold text-blue-600 mb-3">
                        {{ $quotaInfo['free']['used'] }}/{{ $quotaInfo['free']['total'] }}
                    </p>
                    <p class="text-sm text-blue-600 mb-4">Up to 7 days ahead</p>
                    <div class="bg-blue-200 rounded-full h-3">
                        <div class="bg-blue-500 h-3 rounded-full transition-all duration-500"
                            style="width: {{ $quotaInfo['free']['total'] > 0 ? ($quotaInfo['free']['used'] / $quotaInfo['free']['total']) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <div class="quota-card bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-bold text-purple-800">⭐ Premium Booking Quota</h4>
                        <div class="text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded-full">Monthly</div>
                    </div>
                    <p class="text-4xl font-bold text-purple-600 mb-3">
                        {{ $quotaInfo['premium']['used'] }}/{{ $quotaInfo['premium']['total'] }}
                    </p>
                    <p class="text-sm text-purple-600 mb-4">Up to 1 month ahead</p>
                    <div class="bg-purple-200 rounded-full h-3">
                        <div class="bg-purple-500 h-3 rounded-full transition-all duration-500"
                            style="width: {{ $quotaInfo['premium']['total'] > 0 ? ($quotaInfo['premium']['used'] / $quotaInfo['premium']['total']) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <div class="quota-card bg-gradient-to-br from-green-50 to-green-100 border border-green-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-bold text-green-800">📊 Weekly Remaining</h4>
                        <div class="text-xs bg-green-200 text-green-800 px-2 py-1 rounded-full">Available</div>
                    </div>
                    <p class="text-4xl font-bold text-green-600 mb-3">{{ $quotaInfo['weekly_remaining'] }}</p>
                    <p class="text-sm text-green-600 mb-4">This week's balance</p>
                    <a href="{{ route('facilities') }}"
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        🎾 Book Now
                    </a>
                </div>
            </div>

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
                        <div class="booking-card bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-lg p-6 opacity-75">
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

    <style>
        .stat-card {
            animation: slideInUp 0.6s ease-out;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.3s;
        }

        .quota-card {
            animation: slideInUp 0.6s ease-out;
        }

        .quota-card:nth-child(2) {
            animation-delay: 0.1s;
        }

        .quota-card:nth-child(3) {
            animation-delay: 0.2s;
        }

        .booking-card {
            animation: slideInLeft 0.6s ease-out;
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/animejs@3.2.1/lib/anime.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats cards
            anime({
                targets: '.stat-card',
                translateY: [30, 0],
                opacity: [0, 1],
                delay: anime.stagger(100),
                duration: 600,
                easing: 'easeOutQuad'
            });

            // Animate quota cards
            anime({
                targets: '.quota-card',
                translateY: [30, 0],
                opacity: [0, 1],
                delay: anime.stagger(100, {
                    start: 200
                }),
                duration: 600,
                easing: 'easeOutQuad'
            });

            // Animate booking cards
            anime({
                targets: '.booking-card',
                translateX: [-30, 0],
                opacity: [0, 1],
                delay: anime.stagger(50, {
                    start: 400
                }),
                duration: 500,
                easing: 'easeOutQuad'
            });
        });
    </script>
</section>
