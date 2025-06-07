<?php

use function Livewire\Volt\{layout, state, mount};
use Carbon\Carbon;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

layout('components.frontend.app');

state([
    'courtNumber' => 2,
    'currentWeekStart' => null,
    'startDate' => '',
    'endDate' => '',
    'weekDays' => [],
    'timeSlots' => [],
    'bookedSlots' => [],
    'preliminaryBookedSlots' => [],
    'selectedSlots' => [],
    'showConfirmModal' => false,
    'showThankYouModal' => false,
    'showLoginReminder' => false,
    'showCalendarPicker' => false,
    'bookingReference' => '',
    'pendingBookingData' => [],
    'bookingType' => 'free',
    'quotaInfo' => [],
    'canGoBack' => true,
    'canGoForward' => true,
    'weekOffset' => 0,
    'quotaWarning' => '',
    'isLoggedIn' => false,
]);

mount(function () {
    $this->isLoggedIn = auth('tenant')->check();
    $this->currentWeekStart = Carbon::today()->startOfWeek();
    $this->updateWeekData();
    $this->loadQuotaInfo();

    if (session()->has('pending_booking_slots')) {
        $this->selectedSlots = session('pending_booking_slots');
        session()->forget('pending_booking_slots');
        $this->determineBookingType();
    }
});

$updateWeekData = function () {
    $weekStart = $this->currentWeekStart->copy();
    $weekEnd = $weekStart->copy()->addDays(6);

    $this->startDate = $weekStart->format('d/m/Y');
    $this->endDate = $weekEnd->format('d/m/Y');

    $this->generateWeekDays($weekStart);
    $this->generateTimeSlots();
    $this->loadBookedSlots();
    $this->updateNavigationState();
    $this->validateQuotaForSelections();

    // Don't clear selections when changing weeks - preserve them for premium bookings
};

$generateWeekDays = function ($startDate) {
    $days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
    $currentDate = $startDate->copy();
    $this->weekDays = [];

    for ($i = 0; $i < 7; $i++) {
        $isToday = $currentDate->isToday();
        $isPast = $currentDate->isPast() && !$isToday;
        $daysFromNow = Carbon::now()->diffInDays($currentDate, false);
        $isFreePeriod = $daysFromNow <= 7;

        $this->weekDays[] = [
            'name' => $days[$i],
            'date' => $currentDate->format('Y-m-d'),
            'day_number' => $currentDate->format('j'),
            'month_name' => $currentDate->format('M'),
            'is_today' => $isToday,
            'is_past' => $isPast,
            'is_free_period' => $isFreePeriod,
            'formatted_date' => $currentDate->format('D, M j'),
            'days_from_now' => $daysFromNow,
        ];
        $currentDate->addDay();
    }
};

$generateTimeSlots = function () {
    $this->timeSlots = [];
    for ($hour = 8; $hour < 23; $hour++) {
        $this->timeSlots[] = [
            'start' => sprintf('%02d:00', $hour),
            'end' => sprintf('%02d:00', $hour + 1),
        ];
    }
};

$loadBookedSlots = function () {
    $weekStart = $this->currentWeekStart;
    $weekEnd = $weekStart->copy()->addDays(6);

    $bookings = Booking::where('status', '!=', 'cancelled')
        ->whereBetween('date', [$weekStart, $weekEnd])
        ->get();

    $this->bookedSlots = [];
    $this->preliminaryBookedSlots = [];

    foreach ($bookings as $booking) {
        $slotKey = $booking->date->format('Y-m-d') . '-' . $booking->start_time->format('H:i');

        if ($booking->status === 'confirmed') {
            $this->bookedSlots[] = [
                'key' => $slotKey,
                'type' => $booking->booking_type
            ];
        } else {
            $this->preliminaryBookedSlots[] = [
                'key' => $slotKey,
                'type' => $booking->booking_type
            ];
        }
    }
};

$updateNavigationState = function () {
    $today = Carbon::today();
    $maxFutureWeeks = 4;

    $this->canGoBack = $this->currentWeekStart->gt($today->startOfWeek());
    $this->canGoForward = $this->weekOffset < $maxFutureWeeks;
};

$previousWeek = function () {
    if ($this->canGoBack) {
        $this->currentWeekStart = $this->currentWeekStart->subWeek();
        $this->weekOffset--;
        $this->updateWeekData();
    }
};

$nextWeek = function () {
    if ($this->canGoForward) {
        $this->currentWeekStart = $this->currentWeekStart->addWeek();
        $this->weekOffset++;
        $this->updateWeekData();
    }
};

$goToCurrentWeek = function () {
    $this->currentWeekStart = Carbon::today()->startOfWeek();
    $this->weekOffset = 0;
    $this->updateWeekData();
};

$jumpToWeek = function ($weeksFromNow) {
    $this->currentWeekStart = Carbon::today()->startOfWeek()->addWeeks($weeksFromNow);
    $this->weekOffset = $weeksFromNow;
    $this->updateWeekData();
};

$showCalendar = function () {
    $this->showCalendarPicker = true;
};

$selectCalendarWeek = function ($weekStart) {
    $this->currentWeekStart = Carbon::parse($weekStart)->startOfWeek();
    $this->weekOffset = Carbon::today()->startOfWeek()->diffInWeeks($this->currentWeekStart);
    $this->showCalendarPicker = false;
    $this->updateWeekData();
};

$loadQuotaInfo = function () {
    if (auth('tenant')->check()) {
        $tenant = auth('tenant')->user();
        $this->quotaInfo = [
            'free' => $tenant->free_booking_quota,
            'premium' => $tenant->premium_booking_quota,
            'weekly_remaining' => $tenant->remaining_weekly_quota
        ];
    }
};

$validateQuotaForSelections = function () {
    if (!$this->isLoggedIn || empty($this->selectedSlots)) {
        $this->quotaWarning = '';
        return;
    }

    $freeCount = 0;
    $premiumCount = 0;

    foreach ($this->selectedSlots as $slotKey) {
        $parts = explode('-', $slotKey);
        if (count($parts) >= 3) {
            $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
            $daysFromNow = Carbon::now()->diffInDays(Carbon::parse($date), false);

            if ($daysFromNow <= 7) {
                $freeCount++;
            } else {
                $premiumCount++;
            }
        }
    }

    $freeRemaining = $this->quotaInfo['free']['remaining'] ?? 0;
    $premiumRemaining = $this->quotaInfo['premium']['remaining'] ?? 0;

    if ($freeCount > $freeRemaining) {
        $this->quotaWarning = "You've selected {$freeCount} free slots but only have {$freeRemaining} remaining.";
    } elseif ($premiumCount > $premiumRemaining) {
        $this->quotaWarning = "You've selected {$premiumCount} premium slots but only have {$premiumRemaining} remaining.";
    } else {
        $this->quotaWarning = '';
    }
};

$toggleTimeSlot = function ($slotKey) {
    // Check if slot is booked
    $bookedKeys = array_column($this->bookedSlots, 'key');
    $preliminaryKeys = array_column($this->preliminaryBookedSlots, 'key');

    if (in_array($slotKey, $bookedKeys) || in_array($slotKey, $preliminaryKeys)) {
        return;
    }

    // Check if slot is in the past
    $parts = explode('-', $slotKey);
    if (count($parts) >= 4) {
        $slotDateTime = Carbon::createFromFormat('Y-m-d H:i', $parts[0] . '-' . $parts[1] . '-' . $parts[2] . ' ' . $parts[3]);
        if ($slotDateTime->isPast()) {
            return;
        }
    }

    $index = array_search($slotKey, $this->selectedSlots);
    if ($index !== false) {
        unset($this->selectedSlots[$index]);
        $this->selectedSlots = array_values($this->selectedSlots);
    } else {
        $this->selectedSlots[] = $slotKey;
    }

    $this->determineBookingType();
    $this->validateQuotaForSelections();
};

$determineBookingType = function () {
    if (empty($this->selectedSlots)) {
        $this->bookingType = 'free';
        return;
    }

    $hasPremium = false;
    foreach ($this->selectedSlots as $slotKey) {
        $parts = explode('-', $slotKey);
        if (count($parts) >= 3) {
            $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
            $daysFromNow = Carbon::now()->diffInDays(Carbon::parse($date), false);
            if ($daysFromNow > 7) {
                $hasPremium = true;
                break;
            }
        }
    }

    $this->bookingType = $hasPremium ? 'mixed' : 'free';
};

$getSlotType = function ($slotKey) {
    $parts = explode('-', $slotKey);
    if (count($parts) >= 3) {
        $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
        $daysFromNow = Carbon::now()->diffInDays(Carbon::parse($date), false);
        return $daysFromNow <= 7 ? 'free' : 'premium';
    }
    return 'free';
};

$confirmBooking = function () {
    if (count($this->selectedSlots) === 0) {
        return;
    }

    if (!auth('tenant')->check()) {
        session(['pending_booking_slots' => $this->selectedSlots]);
        $this->showLoginReminder = true;
        return;
    }

    if ($this->quotaWarning) {
        session()->flash('error', $this->quotaWarning);
        return;
    }

    $this->prepareBookingData();
    $this->showConfirmModal = true;
};

$prepareBookingData = function () {
    $this->pendingBookingData = [];

    foreach ($this->selectedSlots as $slotKey) {
        if (!str_contains($slotKey, '-')) {
            continue;
        }

        $parts = explode('-', $slotKey);
        if (count($parts) < 4) {
            continue;
        }

        $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
        $time = $parts[3];

        try {
            $dateObj = Carbon::createFromFormat('Y-m-d', $date);
            $timeObj = Carbon::createFromFormat('H:i', $time);

            $isLightRequired = $timeObj->hour >= 18;
            $daysFromNow = Carbon::now()->diffInDays($dateObj, false);
            $bookingType = $daysFromNow <= 7 ? 'free' : 'premium';

            $this->pendingBookingData[] = [
                'date' => $dateObj->format('D, m/d/Y'),
                'time' => $time . ' - ' . $timeObj->copy()->addHour()->format('H:i'),
                'is_light_required' => $isLightRequired,
                'raw_date' => $date,
                'raw_time' => $time,
                'booking_type' => $bookingType,
            ];
        } catch (\Exception $e) {
            Log::error('Error parsing booking slot: ' . $slotKey, ['error' => $e->getMessage()]);
            continue;
        }
    }
};

$processBooking = function () {
    $tenant = auth('tenant')->user();
    $bookings = [];

    foreach ($this->pendingBookingData as $bookingData) {
        try {
            $booking = Booking::create([
                'tenant_id' => $tenant->id,
                'court_id' => $this->courtNumber,
                'date' => $bookingData['raw_date'],
                'start_time' => $bookingData['raw_time'],
                'end_time' => Carbon::createFromFormat('H:i', $bookingData['raw_time'])->addHour()->format('H:i'),
                'status' => 'pending',
                'booking_type' => $bookingData['booking_type'],
                'is_light_required' => $bookingData['is_light_required'],
            ]);

            $booking->calculatePrice();
            $booking->save();

            $bookings[] = $booking;
        } catch (\Exception $e) {
            Log::error('Error creating booking', ['error' => $e->getMessage(), 'data' => $bookingData]);
            continue;
        }
    }

    if (empty($bookings)) {
        session()->flash('error', 'Failed to create bookings. Please try again.');
        return;
    }

    $this->bookingReference = $bookings[0]->generateReference();

    foreach ($bookings as $booking) {
        $booking->update(['booking_reference' => $this->bookingReference]);
    }

    $this->selectedSlots = [];
    $this->showConfirmModal = false;
    $this->showThankYouModal = true;

    $this->loadBookedSlots();
    $this->loadQuotaInfo();
};

$closeModal = function () {
    $this->showConfirmModal = false;
    $this->showThankYouModal = false;
    $this->showLoginReminder = false;
    $this->showCalendarPicker = false;
};

$redirectToLogin = function () {
    return redirect()->route('tenant.login');
};

?>

<section>
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="bg-gradient-to-r from-gray-600 to-gray-800 text-white py-8 text-center relative overflow-hidden">
            <div class="absolute inset-0 bg-black opacity-10"></div>
            <div class="relative z-10">
                <h1 class="text-3xl font-bold tracking-wide">🎾 TENNIS COURT BOOKING</h1>
                <p class="text-gray-200 mt-2">Reserve your perfect playing time</p>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6 bg-white min-h-screen">
            <!-- Title Section -->
            <div class="mb-8">
                <div class="flex justify-between items-start flex-wrap gap-4">
                    <div class="booking-title">
                        <h2 class="text-lg mb-2 font-medium text-gray-600">Select Date & Time</h2>
                        <h3 class="text-3xl font-bold mb-2 text-gray-800">
                            @if($bookingType === 'mixed')
                            Mixed Booking, Court {{ $courtNumber }}
                            @else
                            {{ ucfirst($bookingType) }} Booking, Court {{ $courtNumber }}
                            @endif
                        </h3>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-600 font-medium">{{ $startDate }} - {{ $endDate }}</p>
                        @if($weekOffset === 0)
                        <p class="text-xs text-blue-600 font-bold">📅 Current Week</p>
                        @elseif($weekOffset === 1)
                        <p class="text-xs text-purple-600 font-bold">📅 Next Week</p>
                        @else
                        <p class="text-xs text-purple-600 font-bold">📅 {{ $weekOffset }} weeks ahead</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Login Prompt for Quota -->
            @if(!$isLoggedIn)
            <div class="bg-blue-50 border-l-4 border-blue-400 p-6 mb-6 rounded-r-lg">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>Login to see your booking quota</strong> and make reservations.
                            <a href="{{ route('tenant.login') }}" class="underline hover:text-blue-900 transition-colors">Sign in here</a>
                        </p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Week Navigation -->
            <div class="mb-8">
                <div class="flex items-center justify-between bg-gradient-to-r from-gray-50 to-gray-100 p-6 rounded-xl shadow-sm border">
                    <div class="flex items-center gap-4">
                        <button
                            wire:click="previousWeek"
                            @disabled(!$canGoBack)
                            class="nav-button flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-300 transform hover:scale-105
                                @if($canGoBack)
                                    bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 text-gray-700 cursor-pointer shadow-sm hover:shadow-md
                                @else
                                    bg-gray-200 border border-gray-200 text-gray-400 cursor-not-allowed
                                @endif">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Previous
                        </button>

                        @if($weekOffset > 0)
                        <button
                            wire:click="goToCurrentWeek"
                            class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-all duration-300 transform hover:scale-105 shadow-sm">
                            📅 Current Week
                        </button>
                        @endif

                        <button
                            wire:click="showCalendar"
                            class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition-all duration-300 transform hover:scale-105 shadow-sm">
                            📅 Pick Date
                        </button>
                    </div>

                    <!-- Quick Jump Buttons -->
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-600 mr-2 font-medium">Quick Jump:</span>
                        @for($i = 0; $i <= 4; $i++)
                            @php
                            $jumpDate=\Carbon\Carbon::today()->startOfWeek()->addWeeks($i);
                            $isCurrentWeek = $i === $weekOffset;
                            @endphp
                            <button
                                wire:click="jumpToWeek({{ $i }})"
                                class="quick-jump px-3 py-2 text-xs rounded-full transition-all duration-300 transform hover:scale-110
                                    @if($isCurrentWeek)
                                        bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md
                                    @else
                                        bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 shadow-sm hover:shadow-md
                                    @endif"
                                title="{{ $jumpDate->format('M j') }} - {{ $jumpDate->copy()->addDays(6)->format('M j') }}">
                                @if($i === 0)
                                This Week
                                @elseif($i === 1)
                                Next Week
                                @else
                                +{{ $i }}w
                                @endif
                            </button>
                            @endfor
                    </div>

                    <div class="flex items-center gap-4">
                        <button
                            wire:click="nextWeek"
                            @disabled(!$canGoForward)
                            class="nav-button flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-300 transform hover:scale-105
                                @if($canGoForward)
                                    bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 text-gray-700 cursor-pointer shadow-sm hover:shadow-md
                                @else
                                    bg-gray-200 border border-gray-200 text-gray-400 cursor-not-allowed
                                @endif">
                            Next
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quota Info -->
            @if($isLoggedIn && !empty($quotaInfo))
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="quota-card bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-bold text-blue-800">🆓 Free Booking Quota</h4>
                        <div class="text-xs bg-blue-200 text-blue-800 px-2 py-1 rounded-full">Weekly</div>
                    </div>
                    <p class="text-3xl font-bold text-blue-600 mb-2">
                        {{ $quotaInfo['free']['used'] }}/{{ $quotaInfo['free']['total'] }}
                    </p>
                    <p class="text-sm text-blue-600">Up to 7 days ahead</p>
                    <div class="mt-3 bg-blue-200 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full transition-all duration-500"
                            style="width: {{ $quotaInfo['free']['total'] > 0 ? ($quotaInfo['free']['used'] / $quotaInfo['free']['total']) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <div class="quota-card bg-gradient-to-br from-purple-50 to-purple-100 border border-purple-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-bold text-purple-800">⭐ Premium Booking Quota</h4>
                        <div class="text-xs bg-purple-200 text-purple-800 px-2 py-1 rounded-full">Monthly</div>
                    </div>
                    <p class="text-3xl font-bold text-purple-600 mb-2">
                        {{ $quotaInfo['premium']['used'] }}/{{ $quotaInfo['premium']['total'] }}
                    </p>
                    <p class="text-sm text-purple-600">Up to 1 month ahead</p>
                    <div class="mt-3 bg-purple-200 rounded-full h-2">
                        <div class="bg-purple-500 h-2 rounded-full transition-all duration-500"
                            style="width: {{ $quotaInfo['premium']['total'] > 0 ? ($quotaInfo['premium']['used'] / $quotaInfo['premium']['total']) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <div class="quota-card bg-gradient-to-br from-green-50 to-green-100 border border-green-200 p-6 rounded-xl shadow-sm hover:shadow-md transition-all duration-300">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-bold text-green-800">📊 Weekly Remaining</h4>
                        <div class="text-xs bg-green-200 text-green-800 px-2 py-1 rounded-full">Available</div>
                    </div>
                    <p class="text-3xl font-bold text-green-600 mb-2">{{ $quotaInfo['weekly_remaining'] }}</p>
                    <p class="text-sm text-green-600">This weeks balance</p>
                </div>
            </div>
            @endif

            <!-- Quota Warning -->
            @if($quotaWarning)
            <div class="quota-warning bg-orange-50 border-l-4 border-orange-400 p-4 mb-6 rounded-r-lg">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-orange-700">⚠️ {{ $quotaWarning }}</p>
                    </div>
                </div>
            </div>
            @endif

            <!-- Error Messages -->
            @if (session()->has('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
                {{ session('error') }}
            </div>
            @endif

            <!-- Booking Table -->
            <div class="booking-table-container overflow-x-auto mb-8 border border-gray-300 rounded-xl shadow-lg">
                <table class="w-full border-collapse bg-white">
                    <thead>
                        <tr>
                            @foreach($weekDays as $day)
                            <th @class([ 'border-r border-gray-300 last:border-r-0 p-4 text-center relative' , 'bg-gradient-to-b from-blue-500 to-blue-600 text-white'=> $day['is_today'],
                                'bg-gradient-to-b from-gray-400 to-gray-500 text-white' => $day['is_past'],
                                'bg-gradient-to-b from-blue-700 to-blue-800 text-white' => $day['is_free_period'],
                                'bg-gradient-to-b from-purple-600 to-purple-700 text-white' => !$day['is_today'] && !$day['is_past'] && !$day['is_free_period'],
                                ])>
                                <div class="flex flex-col items-center">
                                    <div class="text-sm font-bold">{{ $day['name'] }}</div>
                                    <div class="text-2xl font-bold">{{ $day['day_number'] }}</div>
                                    <div class="text-xs opacity-90">{{ $day['month_name'] }}</div>
                                    @if($day['is_today'])
                                    <div class="text-xs bg-blue-400 px-2 py-0.5 rounded-full mt-1">


                                        TODAY</div>
                                    @elseif(!$day['is_free_period'] && !$day['is_past'])
                                    <div class="text-xs bg-purple-500 px-2 py-0.5 rounded-full mt-1">PREMIUM</div>
                                    @endif
                                </div>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timeSlots as $slot)
                        <tr class="border-b border-gray-200 last:border-b-0 hover:bg-gray-50 transition-colors duration-200">
                            @foreach($weekDays as $day)
                            @php
                            $slotKey = $day['date'] . '-' . $slot['start'];
                            $slotType = $this->getSlotType($slotKey);

                            $bookedSlot = collect($bookedSlots)->firstWhere('key', $slotKey);
                            $preliminarySlot = collect($preliminaryBookedSlots)->firstWhere('key', $slotKey);

                            $isBooked = $bookedSlot !== null;
                            $isPreliminary = $preliminarySlot !== null;
                            $isSelected = in_array($slotKey, $selectedSlots);

                            $slotDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $day['date'] . ' ' . $slot['start']);
                            $isPastSlot = $slotDateTime->isPast();
                            @endphp
                            <td
                                class="time-slot border-r border-gray-200 last:border-r-0 p-3 text-center text-sm transition-all duration-300 cursor-pointer relative
                                            @if($isPastSlot)
                                                bg-gray-100 text-gray-400 cursor-not-allowed
                                            @elseif($isBooked)
                                                @if($bookedSlot['type'] === 'free')
                                                    bg-red-100 text-red-800 cursor-not-allowed border-l-4 border-red-400
                                                @else
                                                    bg-red-200 text-red-900 cursor-not-allowed border-l-4 border-red-600
                                                @endif
                                            @elseif($isPreliminary)
                                                @if($preliminarySlot['type'] === 'free')
                                                    bg-blue-100 text-blue-800 cursor-not-allowed border-l-4 border-blue-400
                                                @else
                                                    bg-blue-200 text-blue-900 cursor-not-allowed border-l-4 border-blue-600
                                                @endif
                                            @elseif($isSelected)
                                                @if($slotType === 'free')
                                                    bg-green-100 text-green-800 cursor-pointer hover:bg-green-200 transform scale-95 shadow-inner border-l-4 border-green-500
                                                @else
                                                    bg-purple-100 text-purple-800 cursor-pointer hover:bg-purple-200 transform scale-95 shadow-inner border-l-4 border-purple-500
                                                @endif
                                            @else
                                                @if($slotType === 'free')
                                                    cursor-pointer hover:bg-blue-50 hover:shadow-md transform hover:scale-105
                                                @else
                                                    cursor-pointer hover:bg-purple-50 hover:shadow-md transform hover:scale-105
                                                @endif
                                            @endif"
                                wire:click="toggleTimeSlot('{{ $slotKey }}')"
                                title="@if($isPastSlot) Past slot @else {{ $day['formatted_date'] }} {{ $slot['start'] }}-{{ $slot['end'] }} ({{ ucfirst($slotType) }}) @endif">
                                <div class="py-1 font-bold">
                                    {{ $slot['start'] }}
                                </div>
                                <div class="text-xs opacity-75">
                                    {{ $slot['end'] }}
                                </div>

                                @if($isPastSlot)
                                <div class="text-xs text-gray-400 mt-1">Past</div>
                                @elseif($isSelected)
                                <div class="text-xs mt-1 font-bold
                                                @if($slotType === 'free') text-green-700 @else text-purple-700 @endif">
                                    ✓ Selected
                                </div>
                                @elseif($isBooked || $isPreliminary)
                                <div class="text-xs mt-1 font-bold">
                                    @if($isBooked) Booked @else Pending @endif
                                </div>
                                @else
                                <div class="text-xs mt-1 opacity-60">
                                    @if($slotType === 'free') 🆓 Free @else ⭐ Premium @endif
                                </div>
                                @endif

                                @if($slotType === 'premium' && !$isPastSlot && !$isBooked && !$isPreliminary)
                                <div class="absolute top-1 right-1 w-2 h-2 bg-purple-500 rounded-full"></div>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap gap-6 mb-8 items-center text-sm bg-gradient-to-r from-gray-50 to-gray-100 p-6 rounded-xl border">
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-red-100 border-l-4 border-red-400 rounded"></div>
                    <span class="font-medium">🆓 Free Booked</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-red-200 border-l-4 border-red-600 rounded"></div>
                    <span class="font-medium">⭐ Premium Booked</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-blue-100 border-l-4 border-blue-400 rounded"></div>
                    <span class="font-medium">🆓 Free Pending</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-blue-200 border-l-4 border-blue-600 rounded"></div>
                    <span class="font-medium">⭐ Premium Pending</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-green-100 border-l-4 border-green-500 rounded"></div>
                    <span class="font-medium">🆓 Free Selected</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-4 h-4 bg-purple-100 border-l-4 border-purple-500 rounded"></div>
                    <span class="font-medium">⭐ Premium Selected</span>
                </div>
                <div class="text-xs text-gray-600 italic ml-auto max-w-md">
                    *For booking later than 6pm additional IDR 50k/hour will be charged for tennis court lights
                </div>
            </div>

            <!-- Selection Summary -->
            @if(count($selectedSlots) > 0)
            <div class="selection-summary bg-gradient-to-r from-green-50 to-blue-50 border border-green-200 p-6 rounded-xl mb-8 shadow-sm">
                <h4 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
                    🎯 Selected Time Slots ({{ count($selectedSlots) }})
                    @if($bookingType === 'mixed')
                    <span class="text-xs bg-gradient-to-r from-blue-500 to-purple-500 text-white px-2 py-1 rounded-full">Mixed Booking</span>
                    @endif
                </h4>
                <div class="flex flex-wrap gap-3">
                    @foreach($selectedSlots as $slot)
                    @php
                    $parts = explode('-', $slot);
                    if (count($parts) >= 4) {
                    $date = \Carbon\Carbon::createFromFormat('Y-m-d', $parts[0] . '-' . $parts[1] . '-' . $parts[2]);
                    $time = $parts[3];
                    $slotType = $this->getSlotType($slot);
                    }
                    @endphp
                    @if(isset($date) && isset($time))
                    <span class="selected-slot inline-flex items-center px-4 py-2 rounded-full text-sm font-medium transition-all duration-300 hover:scale-105
                                    @if($slotType === 'free')
                                        bg-gradient-to-r from-green-100 to-green-200 text-green-800 border border-green-300
                                    @else
                                        bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 border border-purple-300
                                    @endif">
                        @if($slotType === 'free') 🆓 @else ⭐ @endif
                        {{ $date->format('M j') }} at {{ $time }}
                        <button
                            wire:click="toggleTimeSlot('{{ $slot }}')"
                            class="ml-2 hover:scale-110 transition-transform duration-200
                                            @if($slotType === 'free') text-green-600 hover:text-green-800 @else text-purple-600 hover:text-purple-800 @endif">
                            ✕
                        </button>
                    </span>
                    @endif
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Actions -->
            <div class="flex justify-end">
                <button
                    class="confirm-booking px-8 py-4 font-bold text-sm transition-all duration-500 rounded-xl transform hover:scale-105 shadow-lg
                        @if(count($selectedSlots) === 0)
                            bg-gray-300 text-gray-500 cursor-not-allowed
                        @elseif($quotaWarning)
                            bg-orange-400 text-white cursor-not-allowed
                        @else
                            bg-gradient-to-r from-gray-700 via-gray-800 to-gray-900 text-white hover:from-gray-800 hover:via-gray-900 hover:to-black cursor-pointer hover:shadow-xl
                        @endif"
                    wire:click="confirmBooking"
                    @disabled(count($selectedSlots)===0 || $quotaWarning)>
                    @if($quotaWarning)
                    ⚠️ QUOTA EXCEEDED
                    @else
                    🎾 CONFIRM
                    @if($bookingType === 'mixed')
                    MIXED
                    @else
                    {{ strtoupper($bookingType) }}
                    @endif
                    BOOKING(S)
                    @if(count($selectedSlots) > 0)
                    ({{ count($selectedSlots) }})
                    @endif
                    @endif
                </button>
            </div>
        </div>
    </div>

    <!-- Calendar Picker Modal -->
    @if($showCalendarPicker)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 transform animate-scale-in shadow-2xl">
            <h3 class="text-xl font-bold mb-6 text-center">📅 Select Week</h3>

            <div class="space-y-3 max-h-64 overflow-y-auto">
                @for($i = 0; $i <= 4; $i++)
                    @php
                    $weekStart=\Carbon\Carbon::today()->startOfWeek()->addWeeks($i);
                    $weekEnd = $weekStart->copy()->addDays(6);
                    $isCurrentWeek = $i === $weekOffset;
                    @endphp
                    <button
                        wire:click="selectCalendarWeek('{{ $weekStart->format('Y-m-d') }}')"
                        class="w-full p-4 text-left rounded-lg border transition-all duration-300 hover:scale-105
                                @if($isCurrentWeek)
                                    bg-blue-100 border-blue-300 text-blue-800
                                @else
                                    bg-gray-50 border-gray-200 hover:bg-gray-100
                                @endif">
                        <div class="font-semibold">
                            @if($i === 0)
                            This Week
                            @elseif($i === 1)
                            Next Week
                            @else
                            {{ $i }} weeks ahead
                            @endif
                        </div>
                        <div class="text-sm text-gray-600">
                            {{ $weekStart->format('M j') }} - {{ $weekEnd->format('M j, Y') }}
                        </div>
                    </button>
                    @endfor
            </div>

            <div class="flex justify-end mt-6">
                <button
                    class="px-6 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                    wire:click="closeModal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Other modals remain the same... -->
    @if($showConfirmModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white rounded-xl p-6 max-w-lg w-full mx-4 transform animate-scale-in shadow-2xl">
            <h3 class="text-xl font-bold mb-6">
                @if($bookingType === 'mixed')
                🎾 Mixed Booking Confirmation
                @else
                🎾 {{ ucfirst($bookingType) }} Booking Confirmation
                @endif
            </h3>

            <div class="space-y-4 mb-6">
                @foreach($pendingBookingData as $booking)
                <div class="bg-gray-50 p-4 rounded-lg border">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-semibold">{{ $booking['date'] }}</div>
                            <div class="text-lg">{{ $booking['time'] }}</div>
                            @if($booking['is_light_required'])
                            <div class="text-sm text-orange-600 mt-1">
                                💡 additional IDR 50k/hour for tennis court lights
                            </div>
                            @endif
                        </div>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                    @if($booking['booking_type'] === 'free') bg-blue-100 text-blue-800 @else bg-purple-100 text-purple-800 @endif">
                            @if($booking['booking_type'] === 'free') 🆓 @else ⭐ @endif
                            {{ strtoupper($booking['booking_type']) }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="text-sm text-gray-600 mb-6 bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                <p>💳 *Please process the payment to the Receptionist before using the tennis court</p>
                <p>⚠️ *Please be responsible with your bookings. Failure to comply may result in being blacklisted.</p>
            </div>

            <div class="flex gap-3 justify-end">
                <button
                    class="px-6 py-2 text-gray-600 hover:text-gray-800 transition-colors rounded-lg"
                    wire:click="closeModal">
                    Cancel
                </button>
                <button
                    class="px-6 py-2 bg-gradient-to-r from-gray-700 to-gray-900 text-white rounded-lg hover:from-gray-800 hover:to-black transition-all duration-300 transform hover:scale-105"
                    wire:click="processBooking">
                    🎾 CONFIRM BOOKING(S)
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($showThankYouModal)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4 text-center transform animate-scale-in shadow-2xl">
            <div class="text-6xl mb-4">🎾</div>
            <h3 class="text-xl font-bold mb-4">Thank you for your booking!</h3>
            <div class="text-3xl font-bold text-gray-800 mb-6 bg-gray-100 py-4 rounded-lg">#{{ $bookingReference }}</div>
            <button
                class="px-8 py-3 bg-gradient-to-r from-gray-600 to-gray-800 text-white rounded-lg hover:from-gray-700 hover:to-gray-900 transition-all duration-300 transform hover:scale-105"
                wire:click="closeModal">
                🏠 BACK TO BOOKING
            </button>
        </div>
    </div>
    @endif

    @if($showLoginReminder)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-fade-in">
        <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 transform animate-scale-in shadow-2xl">
            <h3 class="text-lg font-bold mb-4">🔐 Login Required</h3>
            <p class="text-gray-600 mb-6">Please log in to your tenant account to proceed with the booking.</p>
            <div class="flex gap-3 justify-end">
                <button
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors"
                    wire:click="closeModal">
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    wire:click="redirectToLogin">
                    🔑 Login
                </button>
            </div>
        </div>
    </div>
    @endif
</section>
