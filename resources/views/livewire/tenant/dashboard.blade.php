<?php
// TODO: OPTIMIZE THESE QUERIES

namespace App\Http\Livewire\Tenant;

use App\Enum\BookingStatusEnum;
use App\Models\Booking;
use App\Settings\SiteSettings;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.frontend.layouts.app')] class extends Component
{
    public $tenant;

    public $upcomingBookings;

    public $pastBookings;

    public $quotaInfo = [];

    public $stats = [];

    public $activeTab = 'upcoming';

    // Modal properties
    public $showCancelModal = false;

    public $bookingToCancel = null;

    public $cancellationReason = '';

    public function mount()
    {
        $this->tenant = auth('tenant')->user();
        $this->loadBookings();
        $this->loadQuotaInfo();
        $this->loadStats();
    }

    public function loadBookings()
    {
        $this->upcomingBookings = $this->tenant->bookings()
            ->where('date', '>=', Carbon::today()->format('Y-m-d'))
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->with('court')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $this->pastBookings = $this->tenant->bookings()
            ->where('date', '<', Carbon::today()->format('Y-m-d'))
            ->with('court')
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->limit(20)
            ->get();
    }

    public function loadQuotaInfo()
    {
        $siteSettings = app(SiteSettings::class);

        $this->quotaInfo = [
            'free' => $this->tenant->free_booking_quota,
            'premium' => $this->tenant->premium_booking_quota,
            'combined' => $this->tenant->combined_booking_quota,
            'site_limits' => [
                'max_bookings_per_tenant' => $siteSettings->max_bookings_per_tenant,
                'booking_advance_days' => $siteSettings->booking_advance_days,
                'cancellation_hours_limit' => $siteSettings->cancellation_hours_limit,
            ],
            // 'weekly_remaining' => $this->tenant->remaining_weekly_quota,
        ];
    }

    public function loadStats()
    {
        $this->stats = [
            'total_bookings' => $this->tenant->bookings()->count(),
            'this_month' => $this->tenant->bookings()
                ->whereMonth('date', Carbon::now()->month)
                ->whereYear('date', Carbon::now()->year)
                ->count(),
            'confirmed' => $this->tenant->bookings()
                ->where('status', BookingStatusEnum::CONFIRMED)
                ->where('date', '>=', Carbon::today())
                ->count(),
            'pending' => $this->tenant->bookings()
                ->where('status', BookingStatusEnum::PENDING)
                ->where('date', '>=', Carbon::today())
                ->count(),
        ];
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function canCancelBooking($booking)
    {
        $siteSettings = app(SiteSettings::class);

        // Check if cancellations are allowed globally
        if (! $siteSettings->allow_booking_cancellations) {
            return false;
        }

        // Check if booking is in the future
        if ($booking->date->lt(Carbon::today())) {
            return false;
        }

        // Check if booking is already cancelled
        if ($booking->status === BookingStatusEnum::CANCELLED) {
            return false;
        }

        // Check cancellation hours limit
        $bookingDateTime = Carbon::parse($booking->date->format('Y-m-d').' '.$booking->start_time->format('H:i:s'));
        $hoursUntilBooking = Carbon::now()->diffInHours($bookingDateTime, false);

        return $hoursUntilBooking >= $siteSettings->cancellation_hours_limit;
    }

    public function getCancellationMessage($booking)
    {
        $siteSettings = app(SiteSettings::class);

        if (! $siteSettings->allow_booking_cancellations) {
            return 'Booking cancellations are currently disabled.';
        }

        if ($booking->date->lt(Carbon::today())) {
            return 'Cannot cancel past bookings.';
        }

        if ($booking->status === BookingStatusEnum::CANCELLED) {
            return 'This booking has already been cancelled.';
        }

        $bookingDateTime = $booking->date->format('Y-m-d');
        $hoursUntilBooking = Carbon::now()->diffInHours($bookingDateTime, false);
        if ($hoursUntilBooking < $siteSettings->cancellation_hours_limit) {
            return "You cannot cancel this booking as it is less than {$siteSettings->cancellation_hours_limit} hours away.";
        } else {
            return "Cancellations must be made at least {$siteSettings->cancellation_hours_limit} hours before the booking. You have {$hoursUntilBooking} hours remaining.";
        }

        return null; // Can cancel
    }

    public function openCancelModal($bookingId)
    {
        $booking = Booking::find($bookingId);

        if (! $booking || $booking->tenant_id !== $this->tenant->id) {
            session()->flash('error', 'Booking not found or access denied.');

            return;
        }

        if (! $this->canCancelBooking($booking)) {
            $message = $this->getCancellationMessage($booking);
            session()->flash('error', $message);

            return;
        }

        $this->bookingToCancel = $booking;
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
        $this->bookingToCancel = null;
        $this->cancellationReason = '';
    }

    public function confirmCancellation()
    {
        if (! $this->bookingToCancel) {
            session()->flash('error', 'No booking selected for cancellation.');
            $this->closeCancelModal();

            return;
        }

        // Double-check if we can still cancel this booking
        if (! $this->canCancelBooking($this->bookingToCancel)) {
            $message = $this->getCancellationMessage($this->bookingToCancel);
            session()->flash('error', $message);
            $this->closeCancelModal();

            return;
        }
        // Update the booking status
        $this->bookingToCancel->update([
            'status' => BookingStatusEnum::CANCELLED,
            // 'cancelled_by' => $this->tenant->id ?? null,
            'cancelled_at' => Carbon::now(),
            'cancellation_reason' => $this->cancellationReason ?: 'Cancelled by tenant',
        ]);

        // Reload data
        $this->loadBookings();
        $this->loadQuotaInfo();
        $this->loadStats();

        // Show success message
        session()->flash('message', 'Booking cancelled successfully! Your quota has been updated.');

        // Close modal
        $this->closeCancelModal();
    }

    public function getSiteSettings()
    {
        return app(SiteSettings::class);
    }

    public function getTodaysBookingsProperty()
    {
        return $this->upcomingBookings->filter(function ($booking) {
            return $booking->date->isToday();
        })->groupBy(function ($booking) {
            return $booking->court->name ?? 'Court';
        });
    }

    public function getUpcomingBookingsGroupedProperty()
    {
        return $this->upcomingBookings->filter(function ($booking) {
            return $booking->date->isAfter(Carbon::today());
        })->groupBy(function ($booking) {
            return $booking->date->format('Y-m-d');
        });
    }

    public function getPastBookingsGroupedProperty()
    {
        return $this->pastBookings->groupBy(function ($booking) {
            return $booking->date->format('Y-m-d');
        });
    }
}
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

                    <div class="flex flex-col items-end gap-4">
                        <form method="POST" action="{{ route('logout') }}" class="text-right">
                            @csrf
                            <button type="submit" class="py-2 px-4 bg-white rounded-lg shadow-lg text-blue-600 hover:text-blue-800 transition-colors">
                                Logout
                            </button>
                        </form>
                        <div class="text-right">
                            <a href="{{ route('tenant.profile') }}"
                                class="py-2 px-4 bg-white rounded-lg shadow-lg text-blue-600 hover:text-blue-800 transition-colors">
                                Edit Profile
                            </a>
                        </div>
                    </div>
                    <!-- <div class="text-right">
                        <div class="text-sm text-blue-200">{{ Carbon::now()->format('l, F j, Y') }}</div>
                        <div class="text-lg font-semibold">{{ Carbon::now()->format('g:i A') }}</div>
                    </div> -->
                </div>
            </div>
        </div>

        <div class="container py-8">
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

                <div class="stat-card bg-white p-6 rounded-xl shadow-sm border hover:shadow-md transition-all duration-300 ">
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

                <div class="stat-card bg-white p-6 rounded-xl shadow-sm border hover:shadow-md transition-all duration-300 ">
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

                <div class="stat-card bg-white p-6 rounded-xl shadow-sm border hover:shadow-md transition-all duration-300 ">
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="quota-card bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-bold text-blue-800">🆓 Free Booking Used</h4>
                        <div class="text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded-full">Weekly</div>
                    </div>
                    <p class="text-4xl font-bold text-blue-600 mb-3">
                        {{ $quotaInfo['free']['used'] }}
                    </p>
                    <p class="text-sm text-blue-600 mb-4">Up to next week</p>
                </div>

                <div class="quota-card bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-bold text-purple-800">⭐ Premium Booking Used</h4>
                        <div class="text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded-full">Monthly</div>
                    </div>
                    <p class="text-4xl font-bold text-purple-600 mb-3">
                        {{ $quotaInfo['premium']['used'] }}
                    </p>
                    <p class="text-sm text-purple-600 mb-4">Up to end of the month</p>
                </div>

                <div class="quota-card bg-gradient-to-br from-green-50 to-green-100 border border-green-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-bold text-green-800">📊 Weekly Remaining</h4>
                        <div class="text-xs bg-green-200 text-green-800 px-2 py-1 rounded-full">Available</div>
                    </div>
                    <p class="text-4xl font-bold text-green-600 mb-3">{{ $quotaInfo['combined']['remaining'] }}</p>
                    <p class="text-sm text-green-600 mb-4">This week's balance</p>
                    <a href="{{ route('facilities.index') }}"
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        🎾 Book Now
                    </a>
                </div>

                <div class="quota-card bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-bold text-indigo-800">⚙️ System Limits</h4>
                        <div class="text-xs bg-indigo-200 text-indigo-800 px-2 py-1 rounded-full">Site Settings</div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-indigo-600">Max per tenant:</span>
                            <span class="font-semibold text-indigo-800">{{ $quotaInfo['site_limits']['max_bookings_per_tenant'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-indigo-600">Advance days:</span>
                            <span class="font-semibold text-indigo-800">{{ $quotaInfo['site_limits']['booking_advance_days'] }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-indigo-600">Cancel hours:</span>
                            <span class="font-semibold text-indigo-800">{{ $quotaInfo['site_limits']['cancellation_hours_limit'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            @if (session()->has('message'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
                {{ session('message') }}
            </div>
            @endif

            @if (session()->has('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
                {{ session('error') }}
            </div>
            @endif

            <!-- Booking Policy Information -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Cancellation Policy -->
                @if($this->getSiteSettings()->allow_booking_cancellations)
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-blue-800">Booking Cancellation Policy</h4>
                            <div class="mt-2 text-sm text-blue-700">
                                <p>• Cancellations must be made at least <strong>{{ $this->getSiteSettings()->cancellation_hours_limit }} hours</strong> before your booking time</p>
                                <p>• Past bookings cannot be cancelled</p>
                                <p>• Cancelled bookings will free up your quota for future bookings</p>
                            </div>
                        </div>
                    </div>
                </div>
                @else
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-orange-800">Booking Cancellations Temporarily Disabled</h4>
                            <div class="mt-2 text-sm text-orange-700">
                                <p>Booking cancellations are currently disabled by the system administrator. Please contact support if you need to modify your bookings.</p>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                <!-- Booking Limits -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-green-800">Booking System Limits</h4>
                            <div class="mt-2 text-sm text-green-700">
                                <p>• Maximum <strong>{{ $this->getSiteSettings()->max_bookings_per_tenant }} bookings</strong> per tenant</p>
                                <p>• Bookings can be made up to <strong>{{ $this->getSiteSettings()->booking_advance_days }} days</strong> in advance</p>
                                <p>• Free bookings: until the end of next week</p>
                                <p>• Premium bookings: up to the end of the following month</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bookings Section -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <!-- Tab Navigation -->
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <button
                            wire:click="setActiveTab('upcoming')"
                            @class([
                                'py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
                                'border-blue-500 text-blue-600' => $activeTab === 'upcoming',
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $activeTab !== 'upcoming',
                            ])>
                            📅 Upcoming Bookings ({{ count($upcomingBookings) }})
                        </button>
                        <button
                            wire:click="setActiveTab('past')"
                            @class([
                                'py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200',
                                'border-blue-500 text-blue-600' => $activeTab === 'past',
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' => $activeTab !== 'past',
                            ])>
                            📚 Booking History ({{ count($pastBookings) }})
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="p-6">
                    @if($activeTab === 'upcoming')
                    <!-- Today's Bookings -->
                    @if($this->todaysBookings->isNotEmpty())
                    <div class="mb-8">
                        <h3 class="text-lg font-bold text-green-700 mb-2 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            Today's Bookings ({{ \Carbon\Carbon::today()->format('l, d M Y') }})
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($this->todaysBookings as $courtName => $bookings)
                            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                                <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-4 py-3">
                                    <h4 class="text-white font-semibold text-lg">Court {{ $courtName }}</h4>
                                    <p class="text-blue-100 text-sm">{{ count($bookings) }} booking(s) today</p>
                                </div>
                                <div class="p-4 space-y-3">
                                    @foreach($bookings as $booking)
                                        @include('livewire.tenant.partials.booking-card', ['booking' => $booking])
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <!-- Upcoming Bookings Grouped by Date -->
                    @if($this->upcomingBookingsGrouped->isNotEmpty())
                    <div class="mb-8">
                        <h3 class="text-lg font-bold text-blue-700 mb-2 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Upcoming Bookings
                        </h3>
                        @foreach($this->upcomingBookingsGrouped as $date => $bookings)
                        <div class="mb-4">
                            <div class="font-semibold text-gray-700 mb-2">{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($bookings as $booking)
                                    @include('livewire.tenant.partials.booking-card', ['booking' => $booking])
                                @endforeach
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    @if($this->todaysBookings->isEmpty() && $this->upcomingBookingsGrouped->isEmpty())
                    <div class="text-center py-12">
                        <div class="text-6xl mb-4">🎾</div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No upcoming bookings</h3>
                        <p class="text-gray-600 mb-6">Ready to book your next tennis session?</p>
                        <a href="{{ route('facilities.index') }}"
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-lg hover:from-blue-700 hover:to-purple-700 transition-all duration-300 transform hover:scale-105">
                            🎾 Book a Court
                        </a>
                    </div>
                    @endif
                    @else
                    <!-- Past Bookings Grouped by Date -->
                    @if($this->pastBookingsGrouped->isNotEmpty())
                    <div class="mb-8">
                        <h3 class="text-lg font-bold text-gray-900 mb-2 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Booking History
                        </h3>
                        @foreach($this->pastBookingsGrouped as $date => $bookings)
                        <div class="mb-4">
                            <div class="font-semibold text-gray-700 mb-2">{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($bookings as $booking)
                                    @include('livewire.tenant.partials.booking-card', ['booking' => $booking, 'isPast' => true])
                                @endforeach
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

    <!-- Cancellation Confirmation Modal -->
    @if($showCancelModal && $bookingToCancel)
    <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" id="cancelModal">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Cancel Booking</h3>
                    <button wire:click="closeCancelModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Booking Details -->
                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                    <div class="flex items-center space-x-3 mb-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold">
                            {{ $bookingToCancel->court->name }}
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Court {{ $bookingToCancel->court->name }}</h4>
                            <p class="text-sm text-gray-600">
                                {{ $bookingToCancel->date->format('l, F j, Y') }}
                            </p>
                            <p class="text-sm text-gray-600">
                                {{ $bookingToCancel->start_time->format('g:i A') }} - {{ $bookingToCancel->end_time->format('g:i A') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <span @class([
                            'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                            'bg-blue-100 text-blue-800' => $bookingToCancel->booking_type === 'free',
                            'bg-purple-100 text-purple-800' => $bookingToCancel->booking_type !== 'free',
                        ])>
                            @if($bookingToCancel->booking_type === 'free') 🆓 Free @else ⭐ Premium @endif
                        </span>
                        <span @class([
                            'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium',
                            'bg-green-100 text-green-800' => $bookingToCancel->status === BookingStatusEnum::CONFIRMED,
                            'bg-orange-100 text-orange-800' => $bookingToCancel->status !== BookingStatusEnum::CONFIRMED,
                        ])>
                            @if($bookingToCancel->status === BookingStatusEnum::CONFIRMED) ✅ Confirmed @else ⏳ Pending @endif
                        </span>
                    </div>
                </div>

                <!-- Warning Message -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-yellow-800">Important</h4>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>• This action cannot be undone</p>
                                <p>• Your booking quota will be restored</p>
                                <p>• The court will be available for other tenants</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cancellation Reason (Optional) -->
                <div class="mb-4">
                    <label for="cancellation_reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Cancellation Reason (Optional)
                    </label>
                    <textarea
                        wire:model="cancellationReason"
                        id="cancellation_reason"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Please provide a reason for cancellation (optional)..."
                    ></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3">
                    <button
                        wire:click="closeCancelModal"
                        class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Keep Booking
                    </button>
                    <button
                        wire:click="confirmCancellation"
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

@script
    <script>
            // Animate stats cards
            anime.animate(
                '.stat-card',{
                translateY: [30, 0],
                opacity: [0, 1],
                delay: anime.stagger(100),
                duration: 600,
                easing: 'easeOutQuad'
            });

            // Animate quota cards
            anime.animate(
                '.quota-card',{
                translateY: [30, 0],
                opacity: [0, 1],
                delay: anime.stagger(100, {
                    start: 200
                }),
                duration: 600,
                easing: 'easeOutQuad'
            });

            // Modal backdrop click to close
            document.addEventListener('click', function(event) {
                if (event.target.id === 'cancelModal') {
                    @this.closeCancelModal();
                }
            });

            // ESC key to close modal
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && @this.showCancelModal) {
                    @this.closeCancelModal();
                }
            });
    </script>
@endscript
</section>
