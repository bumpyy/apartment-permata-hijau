<?php

use Carbon\Carbon;
use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.frontend.app')] class extends Component {
    public $courtNumber;
    public $selectedDate;
    public $availableTimes = [];
    public $selectedSlots = [];
    public $quotaWarning = '';
    public $quotaInfo;
    public $bookingConfirmed = false;
    public $confirmingBooking = false;
    public $bookingToConfirm;
    public bool $compactView = false;
    public $viewMode = 'weekly';
    public $currentDate;
    public $currentWeekStart;
    public $currentMonthStart;
    public $canGoBack = true;
    public $canGoForward = true;
    public $isLoggedIn = false;
    public $isPremiumBookingOpen = false;
    public $premiumBookingDate;
    public $weekDays = [];
    public $monthDays = [];
    public $timeSlots = [];
    public $bookedSlots = [];
    public $preliminaryBookedSlots = [];
    public $bookingType = 'free';
    public $showTimeSelector = false;
    public $showDatePicker = false;
    public $selectedDateForTime;
    public $availableTimesForDate = [];
    public $datePickerMode = 'day';
    public $selectedMonth;
    public $selectedYear;
    public $availableMonths = [];
    public $availableYears = [];
    public $calendarDays = [];
    public $calendarWeeks = [];
    public $calendarMonths = [];
    public $showConfirmModal = false;
    public $showThankYouModal = false;
    public $showLoginReminder = false;
    public $pendingBookingData = [];
    public $bookingReference;

    public function mount()
    {
        $this->courtNumber = 2;
        $this->selectedDate = now()->format('Y-m-d');
        $this->currentDate = now();
        $this->currentWeekStart = now()->startOfWeek();
        $this->currentMonthStart = now()->startOfMonth();

        // Set premium booking date (25th of current month, or next month if past 25th)
        $this->premiumBookingDate = now()->day >= 25 ?
            now()->addMonth()->day(25) :
            now()->day(25);

        $this->isPremiumBookingOpen = now()->gte($this->premiumBookingDate);
        $this->isLoggedIn = auth('tenant')->check();

        $this->generateAvailableTimesForDate();
        $this->quotaInfo = $this->getQuotaInfo();
        $this->generateTimeSlots();
        $this->generateWeekDays();
        $this->generateMonthDays();
        $this->initializeDatePicker();
        $this->loadBookedSlots();
    }

    public function getQuotaInfo()
    {
        if (!$this->isLoggedIn) {
            return ['weekly_remaining' => 0];
        }

        $tenant = auth('tenant')->user();
        $weeklyBookings = Booking::where('tenant_id', $tenant->id)
            ->whereBetween('date', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->where('status', '!=', 'cancelled')
            ->count();

        $weeklyRemaining = 7 - $weeklyBookings;

        return [
            'weekly_remaining' => $weeklyRemaining,
            'weekly_used' => $weeklyBookings,
            'weekly_total' => 7
        ];
    }

    public function loadBookedSlots()
    {
        // Load booked and preliminary slots for current view period
        $startDate = $this->viewMode === 'weekly' ? $this->currentWeekStart : $this->currentMonthStart->copy()->startOfWeek();
        $endDate = $this->viewMode === 'weekly' ? $this->currentWeekStart->copy()->addWeek() : $this->currentMonthStart->copy()->endOfMonth()->endOfWeek();

        $bookings = Booking::where('court_id', $this->courtNumber)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', '!=', 'cancelled')
            ->get();

        $this->bookedSlots = [];
        $this->preliminaryBookedSlots = [];

        foreach ($bookings as $booking) {
            $slotKey = $booking->date->format('Y-m-d') . '-' . $booking->start_time->format('H:i');
            $slotData = [
                'key' => $slotKey,
                'tenant_name' => $booking->tenant->name ?? 'Unknown',
                'is_own_booking' => $this->isLoggedIn && $booking->tenant_id === auth('tenant')->id()
            ];

            if ($booking->status === 'confirmed') {
                $this->bookedSlots[] = $slotData;
            } else {
                $this->preliminaryBookedSlots[] = $slotData;
            }
        }
    }

    public function updatedSelectedDate()
    {
        $this->selectedSlots = [];
        $this->generateAvailableTimesForDate();
        $this->validateSelections();
    }

    public function generateAvailableTimesForDate($date = null)
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::parse($this->selectedDate);
        $startTime = Carbon::parse('08:00');
        $endTime = Carbon::parse('22:00');
        $interval = 60; // 60 minutes

        $this->availableTimes = [];
        $this->availableTimesForDate = [];

        // Get booked slots for this specific date
        $bookedSlotsForDate = Booking::where('court_id', $this->courtNumber)
            ->where('date', $targetDate->format('Y-m-d'))
            ->where('status', '!=', 'cancelled')
            ->get()
            ->pluck('start_time')
            ->map(function ($time) {
                return $time->format('H:i');
            })
            ->toArray();

        while ($startTime <= $endTime) {
            $time = $startTime->format('H:i');
            $slotKey = $targetDate->format('Y-m-d') . '-' . $time;
            $slotType = $this->getSlotType($slotKey);
            $isBooked = in_array($time, $bookedSlotsForDate);
            $isSelected = in_array($slotKey, $this->selectedSlots);
            $isPast = $startTime->copy()->setDateFrom($targetDate)->isPast();

            // For the main availableTimes array (used by daily view)
            if (!$date) {
                if (!$isBooked) {
                    $this->availableTimes[] = $time;
                }
            }

            // For the modal time selector
            $this->availableTimesForDate[] = [
                'start_time' => $time,
                'end_time' => $startTime->copy()->addHour()->format('H:i'),
                'slot_key' => $slotKey,
                'slot_type' => $slotType,
                'is_available' => !$isBooked && !$isPast && $this->canBookSlot($targetDate),
                'is_booked' => $isBooked,
                'is_selected' => $isSelected,
                'is_past' => $isPast,
                'is_peak' => $startTime->hour >= 18
            ];

            $startTime->addMinutes($interval);
        }
    }

    public function toggleTimeSlot($slotKey)
    {
        if (in_array($slotKey, $this->selectedSlots)) {
            $this->selectedSlots = array_diff($this->selectedSlots, [$slotKey]);
        } else {
            // Extract date from slot key for quota checking
            $parts = explode('-', $slotKey);
            if (count($parts) >= 4) {
                $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];

                // Check if adding this slot exceeds the daily quota (2 hours)
                $dailySlots = array_filter($this->selectedSlots, function ($slot) use ($date) {
                    return str_starts_with($slot, $date);
                });

                // Also check existing bookings for this date (both confirmed and pending)
                if ($this->isLoggedIn) {
                    $existingBookingsForDate = Booking::where('court_id', $this->courtNumber)
                        ->where('date', $date)
                        ->where('status', '!=', 'cancelled')
                        ->where('tenant_id', auth('tenant')->id())
                        ->count();

                    $totalSlotsForDay = count($dailySlots) + $existingBookingsForDate;

                    if ($totalSlotsForDay >= 2) {
                        $this->quotaWarning = 'Maximum 2 hours per day allowed (including pending bookings).';
                        return;
                    }
                }

                $this->selectedSlots[] = $slotKey;
            }
        }

        $this->validateSelections();

        // Refresh available times if we're in the time selector modal
        if ($this->showTimeSelector) {
            $this->generateAvailableTimesForDate($this->selectedDateForTime);
        }
    }

    private function validateSelections()
    {
        if (empty($this->selectedSlots)) {
            $this->quotaWarning = '';
            return;
        }

        $selectedDays = [];
        $dailySlotCounts = [];

        foreach ($this->selectedSlots as $slot) {
            $parts = explode('-', $slot);
            if (count($parts) >= 3) {
                $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
                $selectedDays[$date] = true;

                if (!isset($dailySlotCounts[$date])) {
                    $dailySlotCounts[$date] = 0;
                }
                $dailySlotCounts[$date]++;
            }
        }

        // Check daily quota including existing bookings
        if ($this->isLoggedIn) {
            foreach ($dailySlotCounts as $date => $selectedCount) {
                $existingBookingsForDate = Booking::where('court_id', $this->courtNumber)
                    ->where('date', $date)
                    ->where('status', '!=', 'cancelled')
                    ->where('tenant_id', auth('tenant')->id())
                    ->count();

                $totalForDay = $selectedCount + $existingBookingsForDate;

                if ($totalForDay > 2) {
                    $this->quotaWarning = "Maximum 2 hours per day allowed (including existing bookings).";
                    return;
                }
            }

            // Check weekly quota
            $dayCount = count($selectedDays);
            $remaining = $this->quotaInfo['weekly_remaining'] ?? 0;

            if ($dayCount > $remaining) {
                $this->quotaWarning = "You can only book {$remaining} more days this week.";
            } else {
                $this->quotaWarning = '';
            }
        }
    }

    public function confirmBooking()
    {
        if (!$this->isLoggedIn) {
            $this->showLoginReminder = true;
            return;
        }

        $this->validateSelections();

        if (!empty($this->quotaWarning)) {
            return;
        }

        $tenant = auth('tenant')->user();

        foreach ($this->selectedSlots as $slot) {
            $parts = explode('-', $slot);
            $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
            $time = $parts[3] . ':' . $parts[4];

            Booking::create([
                'tenant_id' => $tenant->id,
                'court_id' => $this->courtNumber,
                'date' => $date,
                'start_time' => $time,
                'status' => 'pending',
            ]);
        }

        $this->selectedSlots = [];
        $this->generateAvailableTimesForDate();
        $this->quotaInfo = $this->getQuotaInfo();
        $this->loadBookedSlots();
        session()->flash('message', 'Booking request sent successfully!');
    }

    public function toggleCompactView()
    {
        $this->compactView = !$this->compactView;
    }

    public function closeDatePicker()
    {
        $this->showDatePicker = false;
    }

    public function generateTimeSlots()
    {
        $this->timeSlots = [];
        $start = Carbon::parse('08:00');
        $end = Carbon::parse('22:00');

        while ($start < $end) {
            $this->timeSlots[] = [
                'start' => $start->format('H:i'),
                'end' => $start->copy()->addHour()->format('H:i'),
                'is_peak' => $start->hour >= 18 // After 6pm
            ];
            $start->addHour();
        }
    }

    public function generateWeekDays()
    {
        $this->weekDays = [];
        $start = $this->currentWeekStart->copy();

        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $this->weekDays[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('D'),
                'day_number' => $date->format('j'),
                'month_name' => $date->format('M'),
                'is_today' => $date->isToday(),
                'is_past' => $date->isPast(),
                'is_bookable' => $this->canBookSlot($date),
                'can_book_free' => $this->canBookFree($date),
                'can_book_premium' => $this->canBookPremium($date),
                'formatted_date' => $date->format('M j, Y')
            ];
        }
    }

    public function generateMonthDays()
    {
        $this->monthDays = [];
        $start = $this->currentMonthStart->copy()->startOfWeek();
        $end = $this->currentMonthStart->copy()->endOfMonth()->endOfWeek();

        while ($start <= $end) {
            // Get booking counts for this date
            $bookingCounts = $this->getDateBookingCounts($start);

            $this->monthDays[] = [
                'date' => $start->format('Y-m-d'),
                'day_number' => $start->format('j'),
                'is_current_month' => $start->month === $this->currentMonthStart->month,
                'is_today' => $start->isToday(),
                'is_past' => $start->isPast(),
                'is_bookable' => $this->canBookSlot($start),
                'can_book_free' => $this->canBookFree($start),
                'can_book_premium' => $this->canBookPremium($start),
                'booking_type' => $this->getDateBookingType($start),
                'booked_count' => $bookingCounts['booked'],
                'pending_count' => $bookingCounts['pending'],
                'selected_count' => $bookingCounts['selected'],
                'available_count' => $bookingCounts['available']
            ];
            $start->addDay();
        }
    }

    public function getDateBookingCounts($date)
    {
        $dateStr = $date->format('Y-m-d');

        // Count booked slots
        $bookedCount = collect($this->bookedSlots)->filter(function ($slot) use ($dateStr) {
            return str_starts_with($slot['key'], $dateStr);
        })->count();

        // Count pending slots
        $pendingCount = collect($this->preliminaryBookedSlots)->filter(function ($slot) use ($dateStr) {
            return str_starts_with($slot['key'], $dateStr);
        })->count();

        // Count selected slots
        $selectedCount = collect($this->selectedSlots)->filter(function ($slot) use ($dateStr) {
            return str_starts_with($slot, $dateStr);
        })->count();

        // Calculate available slots (total slots minus booked/pending)
        $totalSlots = 14; // 8am to 10pm = 14 hours
        $availableCount = $totalSlots - $bookedCount - $pendingCount;

        return [
            'booked' => $bookedCount,
            'pending' => $pendingCount,
            'selected' => $selectedCount,
            'available' => max(0, $availableCount)
        ];
    }

    public function canBookSlot($date)
    {
        return $this->canBookFree($date) || $this->canBookPremium($date);
    }

    public function canBookFree($date)
    {
        $nextWeekStart = now()->addWeek()->startOfWeek();
        $nextWeekEnd = now()->addWeek()->endOfWeek();
        return $date->between($nextWeekStart, $nextWeekEnd);
    }

    public function canBookPremium($date)
    {
        $nextWeekEnd = now()->addWeek()->endOfWeek();
        return $date->gt($nextWeekEnd) && $this->isPremiumBookingOpen;
    }

    public function getDateBookingType($date)
    {
        if ($this->canBookFree($date)) return 'free';
        if ($this->canBookPremium($date)) return 'premium';
        return 'none';
    }

    public function getDateBookingInfo($date)
    {
        return [
            'can_book_free' => $this->canBookFree($date),
            'can_book_premium' => $this->canBookPremium($date),
            'is_bookable' => $this->canBookSlot($date)
        ];
    }

    public function getSlotType($slotKey)
    {
        $parts = explode('-', $slotKey);
        if (count($parts) >= 3) {
            $date = Carbon::createFromFormat('Y-m-d', $parts[0] . '-' . $parts[1] . '-' . $parts[2]);
            return $this->getDateBookingType($date);
        }
        return 'none';
    }

    public function switchView($mode)
    {
        $this->viewMode = $mode;
        if ($mode === 'weekly') {
            $this->generateWeekDays();
        } elseif ($mode === 'monthly') {
            $this->generateMonthDays();
        } elseif ($mode === 'daily') {
            // Don't subtract a day when switching to daily view
            $this->selectedDate = $this->currentDate->format('Y-m-d');
            $this->generateAvailableTimesForDate();
        }
        $this->loadBookedSlots();
    }

    public function previousPeriod()
    {
        if ($this->viewMode === 'weekly') {
            $this->currentWeekStart = $this->currentWeekStart->subWeek();
            $this->generateWeekDays();
        } elseif ($this->viewMode === 'monthly') {
            $this->currentMonthStart = $this->currentMonthStart->subMonth();
            $this->generateMonthDays();
        } elseif ($this->viewMode === 'daily') {
            $this->currentDate = $this->currentDate->subDay();
            $this->selectedDate = $this->currentDate->format('Y-m-d');
            $this->generateAvailableTimesForDate();
        }
        $this->loadBookedSlots();
    }

    public function nextPeriod()
    {
        if ($this->viewMode === 'weekly') {
            $this->currentWeekStart = $this->currentWeekStart->addWeek();
            $this->generateWeekDays();
        } elseif ($this->viewMode === 'monthly') {
            $this->currentMonthStart = $this->currentMonthStart->addMonth();
            $this->generateMonthDays();
        } elseif ($this->viewMode === 'daily') {
            $this->currentDate = $this->currentDate->addDay();
            $this->selectedDate = $this->currentDate->format('Y-m-d');
            $this->generateAvailableTimesForDate();
        }
        $this->loadBookedSlots();
    }

    public function goToToday()
    {
        $this->currentDate = now();
        $this->currentWeekStart = now()->startOfWeek();
        $this->currentMonthStart = now()->startOfMonth();
        $this->selectedDate = now()->format('Y-m-d');

        if ($this->viewMode === 'weekly') {
            $this->generateWeekDays();
        } elseif ($this->viewMode === 'monthly') {
            $this->generateMonthDays();
        } else {
            $this->generateAvailableTimesForDate();
        }
        $this->loadBookedSlots();
    }

    public function showDatePicker()
    {
        $this->showDatePicker = true;
        $this->initializeDatePicker();
    }

    public function closeModal()
    {
        $this->showConfirmModal = false;
        $this->showThankYouModal = false;
        $this->showLoginReminder = false;
        $this->showTimeSelector = false;
        $this->showDatePicker = false;
    }

    public function showTimesForDate($date)
    {
        $this->selectedDateForTime = $date;
        $this->showTimeSelector = true;
        $this->generateAvailableTimesForDate($date);
    }

    public function closeTimeSelector()
    {
        $this->showTimeSelector = false;
    }

    public function initializeDatePicker()
    {
        $this->selectedMonth = $this->currentDate->month;
        $this->selectedYear = $this->currentDate->year;

        $this->availableMonths = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        ];

        $this->availableYears = range(now()->year, now()->year + 2);
        $this->generateCalendarDays();
        $this->generateCalendarWeeks();
        $this->generateCalendarMonths();
    }

    public function generateCalendarDays()
    {
        $this->calendarDays = [];
        $firstDay = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfWeek();
        $lastDay = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->endOfMonth()->endOfWeek();

        $current = $firstDay->copy();
        while ($current <= $lastDay) {
            $this->calendarDays[] = [
                'date' => $current->format('Y-m-d'),
                'day_number' => $current->format('j'),
                'is_current_month' => $current->month === $this->selectedMonth,
                'is_today' => $current->isToday(),
                'is_past' => $current->isPast(),
                'can_book_free' => $this->canBookFree($current),
                'can_book_premium' => $this->canBookPremium($current),
                'booking_type' => $this->getDateBookingType($current),
                'formatted_date' => $current->format('M j, Y')
            ];
            $current->addDay();
        }
    }

    public function generateCalendarWeeks()
    {
        $this->calendarWeeks = [];
        $startOfMonth = Carbon::create($this->selectedYear, $this->selectedMonth, 1);
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $current = $startOfMonth->copy()->startOfWeek();
        $weekNumber = 1;

        while ($current <= $endOfMonth) {
            $weekEnd = $current->copy()->endOfWeek();
            $this->calendarWeeks[] = [
                'week_start' => $current->format('Y-m-d'),
                'week_number' => $weekNumber,
                'formatted_range' => $current->format('M j') . ' - ' . $weekEnd->format('M j'),
                'is_current_week' => now()->between($current, $weekEnd),
                'is_past_week' => $weekEnd->isPast(),
                'can_book_free' => $this->canBookFree($current),
                'can_book_premium' => $this->canBookPremium($current),
                'is_bookable' => $this->canBookSlot($current)
            ];
            $current->addWeek();
            $weekNumber++;
        }
    }

    public function generateCalendarMonths()
    {
        $this->calendarMonths = [];
        $currentYear = $this->selectedYear;

        for ($month = 1; $month <= 12; $month++) {
            $monthStart = Carbon::create($currentYear, $month, 1);
            $this->calendarMonths[] = [
                'month_start' => $monthStart->format('Y-m-d'),
                'month_name' => $monthStart->format('F'),
                'is_current_month' => $monthStart->month === now()->month && $monthStart->year === now()->year,
                'is_past_month' => $monthStart->isPast(),
                'can_book_free' => $this->canBookFree($monthStart),
                'can_book_premium' => $this->canBookPremium($monthStart),
                'booking_type' => $this->getDateBookingType($monthStart),
                'is_bookable' => $this->canBookSlot($monthStart)
            ];
        }
    }

    public function setDatePickerMode($mode)
    {
        $this->datePickerMode = $mode;
    }

    public function selectDate($date)
    {
        $selectedDate = Carbon::parse($date);
        $this->currentDate = $selectedDate;
        $this->selectedDate = $date;

        if ($this->viewMode === 'daily') {
            $this->generateAvailableTimesForDate();
        } elseif ($this->viewMode === 'weekly') {
            $this->currentWeekStart = $selectedDate->startOfWeek();
            $this->generateWeekDays();
        } elseif ($this->viewMode === 'monthly') {
            $this->currentMonthStart = $selectedDate->startOfMonth();
            $this->generateMonthDays();
        }

        $this->loadBookedSlots();
        $this->closeDatePicker();
    }

    public function selectWeek($weekStart)
    {
        $this->currentWeekStart = Carbon::parse($weekStart);
        $this->currentDate = $this->currentWeekStart->copy();

        if ($this->viewMode === 'weekly') {
            $this->generateWeekDays();
        }

        $this->loadBookedSlots();
        $this->closeDatePicker();
    }

    public function selectMonth($monthStart)
    {
        $this->currentMonthStart = Carbon::parse($monthStart);
        $this->currentDate = $this->currentMonthStart->copy();

        if ($this->viewMode === 'monthly') {
            $this->generateMonthDays();
        }

        $this->loadBookedSlots();
        $this->closeDatePicker();
    }

    public function redirectToLogin()
    {
        return redirect()->route('login');
    }

    public function processBooking()
    {
        // Process the actual booking
        $this->showConfirmModal = false;
        $this->showThankYouModal = true;
        $this->bookingReference = 'BK' . rand(1000, 9999);
    }

    public function updatedSelectedMonth()
    {
        $this->generateCalendarDays();
        $this->generateCalendarWeeks();
        $this->generateCalendarMonths();
    }

    public function updatedSelectedYear()
    {
        $this->generateCalendarDays();
        $this->generateCalendarWeeks();
        $this->generateCalendarMonths();
    }
}; ?>

<div>
    <!-- Header -->
    <div class="relative overflow-hidden bg-gradient-to-r from-gray-600 to-gray-800 py-8 text-center text-white">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="relative z-10">
            <h1 class="text-3xl font-bold tracking-wide">🎾 TENNIS COURT BOOKING</h1>
            <p class="mt-2 text-gray-200">Reserve your perfect playing time</p>

            <!-- Booking Status Indicators -->
            <div class="mt-4 flex justify-center gap-4 text-sm">
                <div class="flex items-center gap-2 rounded-full bg-green-600 px-3 py-1">
                    <div class="h-2 w-2 rounded-full bg-green-300"></div>
                    <span>🆓 Free Booking: Next Week</span>
                </div>
                @if($isPremiumBookingOpen)
                <div class="flex items-center gap-2 rounded-full bg-purple-600 px-3 py-1">
                    <div class="h-2 w-2 rounded-full bg-purple-300"></div>
                    <span>⭐ Premium Booking: Open Today!</span>
                </div>
                @else
                <div class="flex items-center gap-2 rounded-full bg-gray-500 px-3 py-1">
                    <div class="h-2 w-2 rounded-full bg-gray-300"></div>
                    <span>⭐ Premium Opens: {{ $premiumBookingDate->format('M j, Y') }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="container mx-auto min-h-screen bg-white px-4 py-6">
        <!-- View Mode Selector with Compact Toggle -->
        <div class="mb-6 flex flex-col sm:flex-row justify-center items-center gap-4">
            <div class="inline-flex rounded-lg border border-gray-300 bg-white p-1 shadow-sm">
                <button
                    wire:click="switchView('weekly')"
                    @class([ 'rounded-md px-4 py-2 text-sm font-medium transition-all duration-200' , 'bg-blue-500 text-white shadow-sm'=> $viewMode === 'weekly',
                    'text-gray-700 hover:bg-gray-100' => $viewMode !== 'weekly'
                    ])>
                    📅 Weekly
                </button>
                <button
                    wire:click="switchView('monthly')"
                    @class([ 'rounded-md px-4 py-2 text-sm font-medium transition-all duration-200' , 'bg-blue-500 text-white shadow-sm'=> $viewMode === 'monthly',
                    'text-gray-700 hover:bg-gray-100' => $viewMode !== 'monthly'
                    ])>
                    📆 Monthly
                </button>
                <button
                    wire:click="switchView('daily')"
                    @class([ 'rounded-md px-4 py-2 text-sm font-medium transition-all duration-200' , 'bg-blue-500 text-white shadow-sm'=> $viewMode === 'daily',
                    'text-gray-700 hover:bg-gray-100' => $viewMode !== 'daily'
                    ])>
                    🕐 Daily
                </button>
            </div>

            <!-- Compact View Toggle -->
            <button
                wire:click="toggleCompactView"
                @class([ 'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-200' , 'bg-gray-600 text-white'=> $compactView,
                'bg-gray-100 text-gray-700 hover:bg-gray-200' => !$compactView
                ])>
                @if($compactView)
                📱 Compact
                @else
                🖥️ Full
                @endif
            </button>
        </div>

        <!-- Navigation Controls -->
        <div @class([ 'mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 shadow-sm' , 'p-2'=> $compactView,
            'p-4' => !$compactView
            ])>
            <button
                wire:click="previousPeriod"
                @class([ 'flex items-center gap-2 rounded-lg transition-all duration-300' , 'px-2 py-1 text-sm'=> $compactView,
                'px-4 py-2' => !$compactView,
                'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 cursor-pointer shadow-sm'
                ])>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                @if(!$compactView) Previous @endif
            </button>

            <div class="flex items-center gap-2">
                <div class="text-center">
                    @if($viewMode === 'weekly')
                    <h3 @class([ 'font-semibold' , 'text-sm'=> $compactView,
                        'text-lg' => !$compactView
                        ])>
                        {{ $currentWeekStart->format('M j') }} - {{ $currentWeekStart->copy()->addDays(6)->format('M j, Y') }}
                    </h3>
                    @elseif($viewMode === 'monthly')
                    <h3 @class([ 'font-semibold' , 'text-sm'=> $compactView,
                        'text-lg' => !$compactView
                        ])>{{ $currentMonthStart->format('F Y') }}</h3>
                    @else
                    <h3 @class([ 'font-semibold' , 'text-sm'=> $compactView,
                        'text-lg' => !$compactView
                        ])>{{ $currentDate->format('l, F j, Y') }}</h3>
                    @endif
                </div>

                <!-- Date Picker Button -->
                <button
                    wire:click="showDatePicker"
                    @class([ 'rounded-lg bg-purple-100 text-purple-700 transition-all duration-300 hover:bg-purple-200' , 'px-2 py-1 text-xs'=> $compactView,
                    'px-3 py-1 ml-2' => !$compactView
                    ])>
                    📅 @if(!$compactView) Jump to Date @endif
                </button>
            </div>

            <div class="flex items-center gap-2">
                <button
                    wire:click="goToToday"
                    @class([ 'rounded-lg bg-blue-100 text-blue-700 transition-all duration-300 hover:bg-blue-200' , 'px-2 py-1 text-xs'=> $compactView,
                    'px-4 py-2' => !$compactView
                    ])>
                    📅 @if(!$compactView) Today @endif
                </button>

                <button
                    wire:click="nextPeriod"
                    @class([ 'flex items-center gap-2 rounded-lg transition-all duration-300' , 'px-2 py-1 text-sm'=> $compactView,
                    'px-4 py-2' => !$compactView,
                    'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 cursor-pointer shadow-sm'
                    ])>
                    @if(!$compactView) Next @endif
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Booking Rules Info -->
        @if(!$compactView)
        <div class="mb-6 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-purple-50 p-4">
            <h3 class="mb-2 font-bold text-gray-800">📋 Booking Rules</h3>
            <div class="grid gap-2 text-sm md:grid-cols-2">
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 rounded-full bg-green-500"></div>
                    <span><strong>Free Booking:</strong> Next week only ({{ Carbon::today()->addWeek()->startOfWeek()->format('M j') }} - {{ Carbon::today()->addWeek()->endOfWeek()->format('M j') }})</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-3 w-3 rounded-full bg-purple-500"></div>
                    <span><strong>Premium Booking:</strong> Beyond next week @if($isPremiumBookingOpen)(Open Now!)@else(Opens {{ $premiumBookingDate->format('M j') }})@endif</span>
                </div>
            </div>
        </div>
        @endif

        <!-- Login Prompt -->
        @if(!$isLoggedIn)
        <div @class([ 'mb-6 rounded-r-lg border-l-4 border-blue-400 bg-blue-50' , 'p-3'=> $compactView,
            'p-6' => !$compactView
            ])>
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p @class([ 'text-blue-700' , 'text-xs'=> $compactView,
                        'text-sm' => !$compactView
                        ])>
                        <strong>Login to see your booking quota</strong> and make reservations.
                        <a class="underline transition-colors hover:text-blue-900" href="{{ route('login') }}">Sign in here</a>
                    </p>
                </div>
            </div>
        </div>
        @endif

        <!-- Quota Display -->
        @if($isLoggedIn && !empty($quotaInfo))
        <div @class([ 'mb-6 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100 shadow-sm' , 'p-3'=> $compactView,
            'p-6' => !$compactView
            ])>
            <div class="flex items-center justify-between">
                <div>
                    <h3 @class([ 'font-bold text-blue-800' , 'text-sm'=> $compactView,
                        'text-lg' => !$compactView
                        ])>Weekly Quota</h3>
                    @if(!$compactView)
                    <p class="text-sm text-blue-600">Maximum 3 days per week, 2 hours per day</p>
                    @endif
                </div>
                <div class="text-right">
                    <div @class([ 'font-bold text-blue-600' , 'text-xl'=> $compactView,
                        'text-3xl' => !$compactView
                        ])>
                        {{ $quotaInfo['weekly_used'] ?? 0 }}/{{ $quotaInfo['weekly_total'] ?? 3 }}
                    </div>
                    <div @class([ 'text-blue-600' , 'text-xs'=> $compactView,
                        'text-sm' => !$compactView
                        ])>Days used this week</div>
                </div>
            </div>
            @if(($quotaInfo['weekly_remaining'] ?? 0) > 0)
            <div @class([ 'mt-2 text-green-600' , 'text-xs'=> $compactView,
                'text-sm' => !$compactView
                ])>
                ✅ You can book {{ $quotaInfo['weekly_remaining'] }} more days this week
            </div>
            @else
            <div @class([ 'mt-2 text-red-600' , 'text-xs'=> $compactView,
                'text-sm' => !$compactView
                ])>
                ⚠️ You have reached your weekly booking limit
            </div>
            @endif
        </div>
        @endif

        <!-- Quota Warning -->
        @if($quotaWarning)
        <div @class([ 'mb-6 rounded-r-lg border-l-4 border-orange-400 bg-orange-50' , 'p-3'=> $compactView,
            'p-4' => !$compactView
            ])>
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p @class([ 'text-orange-700' , 'text-xs'=> $compactView,
                        'text-sm' => !$compactView
                        ])>⚠️ {{ $quotaWarning }}</p>
                </div>
            </div>
        </div>
        @endif

        <!-- Weekly View -->
        @if($viewMode === 'weekly')
        <div class="mb-8 overflow-x-auto rounded-xl border border-gray-300 shadow-lg">
            <table class="w-full border-collapse bg-white">
                <thead>
                    <tr>
                        <th @class([ 'border-r border-gray-300 bg-gray-100 text-left font-semibold text-gray-700' , 'p-2 text-xs'=> $compactView,
                            'p-4' => !$compactView
                            ])>Time</th>
                        @foreach($weekDays as $day)
                        <th @class([ 'border-r border-gray-300 last:border-r-0 text-white text-center relative' , 'p-1'=> $compactView,
                            'p-4' => !$compactView,
                            'bg-gradient-to-b from-blue-500 to-blue-600' => $day['is_today'],
                            'bg-gradient-to-b from-gray-400 to-gray-500' => $day['is_past'] && !$day['is_today'],
                            'bg-gradient-to-b from-green-600 to-green-700' => $day['can_book_free'] && !$day['is_today'] && !$day['is_past'],
                            'bg-gradient-to-b from-purple-600 to-purple-700' => $day['can_book_premium'] && !$day['is_today'] && !$day['can_book_free'] && !$day['is_past'],
                            'bg-gradient-to-b from-gray-300 to-gray-400' => !$day['is_bookable'] && !$day['is_today'] && !$day['is_past']
                            ])>
                            <div class="flex flex-col items-center">
                                <div @class([ 'font-bold' , 'text-xs'=> $compactView,
                                    'text-sm' => !$compactView
                                    ])>{{ $compactView ? substr($day['day_name'], 0, 1) : $day['day_name'] }}</div>
                                <div @class([ 'font-bold' , 'text-sm'=> $compactView,
                                    'text-2xl' => !$compactView
                                    ])>{{ $day['day_number'] }}</div>
                                @if(!$compactView)
                                <div class="opacity-90 text-xs">{{ $day['month_name'] }}</div>
                                @endif

                                @if($day['is_today'])
                                <div @class([ 'mt-1 rounded-full bg-blue-400 px-1 py-0.5 text-xs font-bold' , 'text-xs'=> $compactView
                                    ])>{{ $compactView ? '●' : 'TODAY' }}</div>
                                @elseif($day['is_past'])
                                <div @class([ 'mt-1 rounded-full bg-gray-300 px-1 py-0.5 text-xs' , 'text-xs'=> $compactView
                                    ])>{{ $compactView ? '✕' : 'PAST' }}</div>
                                @elseif($day['can_book_free'])
                                <div @class([ 'mt-1 rounded-full bg-green-400 px-1 py-0.5 text-xs font-bold' , 'text-xs'=> $compactView
                                    ])>{{ $compactView ? 'F' : '🆓 FREE' }}</div>
                                @elseif($day['can_book_premium'])
                                <div @class([ 'mt-1 rounded-full bg-purple-400 px-1 py-0.5 text-xs font-bold' , 'text-xs'=> $compactView
                                    ])>{{ $compactView ? 'P' : '⭐ PREMIUM' }}</div>
                                @else
                                <div @class([ 'mt-1 rounded-full bg-gray-300 px-1 py-0.5 text-xs' , 'text-xs'=> $compactView
                                    ])>{{ $compactView ? '🔒' : 'LOCKED' }}</div>
                                @endif
                            </div>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeSlots as $slot)
                    <tr class="border-b border-gray-200 transition-colors duration-200 last:border-b-0 hover:bg-gray-50">
                        <td @class([ 'border-r border-gray-300 bg-gray-50 font-medium text-gray-700' , 'p-2'=> $compactView,
                            'p-4' => !$compactView
                            ])>
                            <div @class([ 'text-xs'=> $compactView,
                                'text-sm' => !$compactView
                                ])>{{ $slot['start'] }}</div>
                            @if(!$compactView)
                            <div class="text-xs text-gray-500">{{ $slot['end'] }}</div>
                            @endif
                        </td>
                        @foreach($weekDays as $day)
                        @php
                        $slotKey = $day['date'] . '-' . $slot['start'];
                        $slotType = $this->getSlotType($slotKey);

                        $bookedSlot = collect($bookedSlots)->firstWhere('key', $slotKey);
                        $preliminarySlot = collect($preliminaryBookedSlots)->firstWhere('key', $slotKey);

                        $isBooked = $bookedSlot !== null;
                        $isPreliminary = $preliminarySlot !== null;
                        $isSelected = in_array($slotKey, $selectedSlots);

                        $slotDateTime = Carbon::createFromFormat('Y-m-d H:i', $day['date'] . ' ' . $slot['start']);
                        $isPastSlot = $slotDateTime->isPast();
                        $canBook = $day['is_bookable'] && !$isPastSlot && !$isBooked && !$isPreliminary;

                        $showBookingInfo = ($isPastSlot || !$day['is_bookable']) && ($isBooked || $isPreliminary);
                        @endphp
                        <td
                            @class([ 'time-slot text-center transition-all duration-200' , 'p-1'=> $compactView,
                            'p-3' => !$compactView,
                            'bg-gray-100 text-gray-400' => ($isPastSlot || !$day['is_bookable']) && !$showBookingInfo,
                            'bg-red-100 text-red-800 cursor-pointer border-l-4 border-red-400' => $isBooked,
                            'bg-yellow-100 text-yellow-800 cursor-pointer border-l-4 border-yellow-400' => $isPreliminary,
                            'bg-green-100 text-green-800 border-l-4 border-green-500 transform scale-95 shadow-inner' => $isSelected && $slotType === 'free',
                            'bg-purple-100 text-purple-800 border-l-4 border-purple-500 transform scale-95 shadow-inner' => $isSelected && $slotType === 'premium',
                            'hover:bg-green-50 hover:shadow-md transform hover:scale-105 cursor-pointer' => $canBook && $slotType === 'free' && !$isSelected,
                            'hover:bg-purple-50 hover:shadow-md transform hover:scale-105 cursor-pointer' => $canBook && $slotType === 'premium' && !$isSelected,
                            ])
                            @if($canBook)
                            wire:click="toggleTimeSlot('{{ $slotKey }}')"
                            @endif
                            @if($showBookingInfo)
                            title="@if($isBooked)Booked by: {{ $bookedSlot['tenant_name'] ?? 'Unknown' }}@else Pending booking @endif"
                            @else
                            title="@if($isPastSlot) Past slot @elseif(!$day['is_bookable']) Not available for booking @else {{ $day['formatted_date'] ?? $day['date'] }} {{ $slot['start'] }}-{{ $slot['end'] }} ({{ ucfirst($slotType) }}) @endif"
                            @endif>

                            @if($isPastSlot && !$showBookingInfo)
                            <div class="text-xs text-gray-400">-</div>
                            @elseif(!$day['is_bookable'] && !$isPastSlot && !$showBookingInfo)
                            <div class="text-xs text-gray-400">🔒</div>
                            @elseif($isSelected)
                            <div @class([ 'font-bold flex items-center justify-center' , 'text-xs'=> $compactView || !$compactView,
                                'text-green-700' => $slotType === 'free',
                                'text-purple-700' => $slotType === 'premium'
                                ])>
                                @if($compactView)
                                <div class="flex flex-col items-center">
                                    <div class="text-lg">✓</div>
                                    <div class="text-xs">{{ $slotType === 'free' ? 'F' : 'P' }}</div>
                                </div>
                                @else
                                ✓ Selected
                                @endif
                            </div>
                            @elseif($isBooked)
                            <div @class([ 'font-bold text-red-700 flex items-center justify-center' , 'text-xs'=> $compactView || !$compactView
                                ])>
                                @if($compactView)
                                <div class="flex flex-col items-center">
                                    <div class="text-lg">●</div>
                                    <div class="text-xs">{{ ($bookedSlot['is_own_booking'] ?? false) ? 'YOU' : 'BKD' }}</div>
                                </div>
                                @else
                                @if($bookedSlot['is_own_booking'] ?? false)
                                Your Booking
                                @else
                                Booked
                                @endif
                                @endif
                            </div>
                            @elseif($isPreliminary)
                            <div @class([ 'font-bold text-yellow-700 flex items-center justify-center' , 'text-xs'=> $compactView || !$compactView
                                ])>
                                @if($compactView)
                                <div class="flex flex-col items-center">
                                    <div class="text-lg">⏳</div>
                                    <div class="text-xs">{{ ($preliminarySlot['is_own_booking'] ?? false) ? 'YOU' : 'PND' }}</div>
                                </div>
                                @else
                                @if($preliminarySlot['is_own_booking'] ?? false)
                                Your Pending
                                @else
                                Pending
                                @endif
                                @endif
                            </div>
                            @elseif($canBook)
                            <div @class([ 'opacity-60 flex items-center justify-center' , 'text-xs'=> $compactView || !$compactView
                                ])>
                                @if($compactView)
                                <div class="flex flex-col items-center">
                                    <div class="text-sm">{{ $slotType === 'free' ? '🆓' : '⭐' }}</div>
                                    <div class="text-xs">{{ $slotType === 'free' ? 'F' : 'P' }}</div>
                                </div>
                                @else
                                @if($slotType === 'free')
                                🆓 Free
                                @else
                                ⭐ Premium
                                @endif
                                @endif
                            </div>
                            @endif

                            @if($slot['is_peak'] && $canBook && !$compactView)
                            <div class="mt-1 text-xs text-orange-600">💡</div>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Monthly View -->
        @if($viewMode === 'monthly')
        <div class="mb-8 overflow-hidden rounded-xl border border-gray-300 shadow-lg">
            <div class="grid grid-cols-7 gap-0">
                <!-- Day headers -->
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                <div @class([ 'bg-gray-100 text-center font-semibold text-gray-700' , 'p-2 text-xs'=> $compactView,
                    'p-4' => !$compactView
                    ])>{{ $compactView ? substr($dayName, 0, 1) : $dayName }}</div>
                @endforeach

                <!-- Calendar days -->
                @foreach($monthDays as $day)
                <div @class([ 'border-r border-b border-gray-200 transition-all duration-200 relative' , 'aspect-square p-1'=> $compactView,
                    'aspect-square p-2' => !$compactView,
                    'bg-white hover:bg-gray-50' => $day['is_current_month'] && !$day['is_past'],
                    'bg-gray-50 text-gray-400' => !$day['is_current_month'] || $day['is_past'],
                    'bg-blue-100 border-blue-300' => $day['is_today'],
                    'cursor-pointer hover:shadow-md' => $day['is_bookable'] && $day['is_current_month'],
                    ])
                    @if($day['is_bookable'] && $day['is_current_month'])
                    wire:click="showTimesForDate('{{ $day['date'] }}')"
                    @endif>
                    <div class="flex h-full flex-col">
                        <div class="flex items-start justify-between">
                            <div @class([ 'font-medium' , 'text-xs'=> $compactView,
                                'text-sm' => !$compactView,
                                'text-blue-600 font-bold' => $day['is_today'],
                                'text-gray-900' => $day['is_current_month'] && !$day['is_today'] && !$day['is_past'],
                                'text-gray-400' => !$day['is_current_month'] || $day['is_past']
                                ])>
                                {{ $day['day_number'] }}
                            </div>

                            <!-- Booking indicators in top right -->
                            @if($day['is_current_month'] && !$day['is_past'])
                            <div class="flex flex-col items-end gap-0.5">
                                @if($day['selected_count'] > 0)
                                <div class="w-2 h-2 bg-green-500 rounded-full" title="{{ $day['selected_count'] }} selected"></div>
                                @endif
                                @if($day['booked_count'] > 0)
                                <div class="w-2 h-2 bg-red-500 rounded-full" title="{{ $day['booked_count'] }} booked"></div>
                                @endif
                                @if($day['pending_count'] > 0)
                                <div class="w-2 h-2 bg-yellow-500 rounded-full" title="{{ $day['pending_count'] }} pending"></div>
                                @endif
                            </div>
                            @endif
                        </div>

                        @if($day['is_past'])
                        <div @class([ 'mt-1 text-gray-400' , 'text-xs'=> $compactView || !$compactView
                            ])>@if($compactView) - @else Past @endif</div>
                        @elseif($day['is_bookable'] && $day['is_current_month'])
                        <div class="mt-1 flex-1 space-y-1">
                            @if($day['can_book_free'])
                            <div @class([ 'rounded bg-green-100 text-green-700' , 'px-1 py-0.5 text-xs'=> !$compactView,
                                'text-xs' => $compactView
                                ])>🆓 @if(!$compactView) Free @endif</div>
                            @endif
                            @if($day['can_book_premium'])
                            <div @class([ 'rounded bg-purple-100 text-purple-700' , 'px-1 py-0.5 text-xs'=> !$compactView,
                                'text-xs' => $compactView
                                ])>⭐ @if(!$compactView) Premium @endif</div>
                            @endif

                            <!-- Booking counts at bottom -->
                            @if(!$compactView && ($day['booked_count'] > 0 || $day['pending_count'] > 0 || $day['selected_count'] > 0 || $day['available_count'] < 14))
                                <div class="text-xs text-gray-600 mt-auto">
                                @if($day['available_count'] > 0)
                                <span class="text-green-600">{{ $day['available_count'] }} free</span>
                                @endif
                                @if($day['booked_count'] > 0)
                                <span class="text-red-600">{{ $day['booked_count'] }} booked</span>
                                @endif
                                @if($day['pending_count'] > 0)
                                <span class="text-yellow-600">{{ $day['pending_count'] }} pending</span>
                                @endif
                        </div>
                        @endif

                        @if(!$compactView)
                        <div class="text-xs text-blue-600 font-medium">Click to book</div>
                        @endif
                    </div>
                    @elseif($day['is_current_month'])
                    <div @class([ 'mt-1 text-gray-400' , 'text-xs'=> $compactView || !$compactView
                        ])>🔒 @if(!$compactView) Locked @endif</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Daily View -->
    @if($viewMode === 'daily')
    <div class="mb-8 rounded-xl border border-gray-300 bg-white shadow-lg">
        <div @class([ 'border-b border-gray-200 bg-gray-50' , 'p-2 rounded-t-xl'=> $compactView,
            'p-4 rounded-t-xl' => !$compactView
            ])>
            <div class="flex items-center justify-between">
                <h3 @class([ 'font-semibold text-gray-800' , 'text-sm'=> $compactView,
                    'text-lg' => !$compactView
                    ])>
                    {{ $currentDate->format($compactView ? 'M j, Y' : 'l, F j, Y') }}
                </h3>
                <div class="flex items-center gap-2">
                    @php $dayInfo = $this->getDateBookingInfo($currentDate); @endphp
                    @if($currentDate->isPast())
                    <span @class([ 'rounded-full bg-gray-200 text-gray-600' , 'px-2 py-1 text-xs'=> !$compactView,
                        'px-1 text-xs' => $compactView
                        ])>Past @if(!$compactView) Date @endif</span>
                    @elseif($dayInfo['can_book_free'])
                    <span @class([ 'rounded-full bg-green-200 text-green-700' , 'px-2 py-1 text-xs'=> !$compactView,
                        'px-1 text-xs' => $compactView
                        ])>🆓 @if(!$compactView) Free Booking @endif</span>
                    @elseif($dayInfo['can_book_premium'])
                    <span @class([ 'rounded-full bg-purple-200 text-purple-700' , 'px-2 py-1 text-xs'=> !$compactView,
                        'px-1 text-xs' => $compactView
                        ])>⭐ @if(!$compactView) Premium Booking @endif</span>
                    @else
                    <span @class([ 'rounded-full bg-gray-200 text-gray-600' , 'px-2 py-1 text-xs'=> !$compactView,
                        'px-1 text-xs' => $compactView
                        ])>🔒 @if(!$compactView) Locked @endif</span>
                    @endif
                </div>
            </div>
        </div>
        <div @class([ 'grid gap-2' , 'p-2 grid-cols-4'=> $compactView,
            'p-4 sm:grid-cols-2 lg:grid-cols-3' => !$compactView
            ])>
            @foreach($timeSlots as $slot)
            @php
            $slotKey = $currentDate->format('Y-m-d') . '-' . $slot['start'];
            $slotType = $this->getSlotType($slotKey);
            $isSelected = in_array($slotKey, $selectedSlots);

            $bookedSlot = collect($bookedSlots)->firstWhere('key', $slotKey);
            $preliminarySlot = collect($preliminaryBookedSlots)->firstWhere('key', $slotKey);
            $isBooked = $bookedSlot !== null;
            $isPreliminary = $preliminarySlot !== null;

            $slotDateTime = Carbon::createFromFormat('Y-m-d H:i', $currentDate->format('Y-m-d') . ' ' . $slot['start']);
            $isPastSlot = $slotDateTime->isPast();
            $canBook = $this->canBookSlot($currentDate) && !$isPastSlot && !$isBooked && !$isPreliminary;
            @endphp
            <div
                @class([ 'rounded-lg border text-center transition-all duration-200' , 'p-2'=> $compactView,
                'p-4' => !$compactView,
                'bg-gray-100 text-gray-400' => $isPastSlot && !$isBooked && !$isPreliminary,
                'bg-red-100 text-red-800 border-red-300' => $isBooked,
                'bg-yellow-100 text-yellow-800 border-yellow-300' => $isPreliminary,
                'bg-green-100 text-green-800 border-green-300 cursor-pointer hover:bg-green-200' => $canBook && $slotType === 'free' && !$isSelected,
                'bg-purple-100 text-purple-800 border-purple-300 cursor-pointer hover:bg-purple-200' => $canBook && $slotType === 'premium' && !$isSelected,
                'bg-green-200 text-green-900 border-green-400 shadow-inner' => $isSelected && $slotType === 'free',
                'bg-purple-200 text-purple-900 border-purple-400 shadow-inner' => $isSelected && $slotType === 'premium',
                ])
                @if($canBook)
                wire:click="toggleTimeSlot('{{ $slotKey }}')"
                @endif>
                <div @class([ 'font-semibold' , 'text-xs'=> $compactView,
                    '' => !$compactView
                    ])>{{ $slot['start'] }}@if(!$compactView) - {{ $slot['end'] }}@endif</div>
                @if($isPastSlot && !$isBooked && !$isPreliminary)
                <div class="text-xs">@if($compactView) - @else Past @endif</div>
                @elseif($isBooked)
                <div class="text-xs">
                    @if($bookedSlot['is_own_booking'] ?? false)
                    @if($compactView) Yours @else Your Booking @endif
                    @else
                    Booked
                    @endif
                </div>
                @elseif($isPreliminary)
                <div class="text-xs">
                    @if($preliminarySlot['is_own_booking'] ?? false)
                    @if($compactView) Pending @else Your Pending @endif
                    @else
                    Pending
                    @endif
                </div>
                @elseif($isSelected)
                <div class="text-xs">✓ @if(!$compactView) Selected @endif</div>
                @elseif($canBook)
                <div class="text-xs">{{ $slotType === 'free' ? '🆓' : '⭐' }} @if(!$compactView){{ $slotType === 'free' ? ' Free' : ' Premium' }}@endif</div>
                @if($slot['is_peak'] && !$compactView)
                <div class="text-xs text-orange-600">💡 Lights required</div>
                @endif
                @else
                <div class="text-xs text-gray-400">🔒 @if(!$compactView) Locked @endif</div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Selection Summary -->
    @if(count($selectedSlots) > 0)
    <div @class([ 'mb-8 rounded-xl border border-green-200 bg-gradient-to-r from-green-50 to-blue-50 shadow-sm' , 'p-3'=> $compactView,
        'p-6' => !$compactView
        ])>
        <h4 @class([ 'mb-4 flex items-center gap-2 font-bold text-gray-800' , 'text-sm mb-2'=> $compactView,
            '' => !$compactView
            ])>
            🎯 Selected Time Slots ({{ count($selectedSlots) }})
            @if($bookingType === 'mixed')
            <span @class([ 'rounded-full bg-gradient-to-r from-blue-500 to-purple-500 text-white' , 'px-2 py-1 text-xs'=> !$compactView,
                'px-1 text-xs' => $compactView
                ])>@if($compactView) Mixed @else Mixed Booking @endif</span>
            @endif
        </h4>
        <div @class([ 'flex flex-wrap' , 'gap-1'=> $compactView,
            'gap-3' => !$compactView
            ])>
            @foreach($selectedSlots as $slot)
            @php
            $parts = explode('-', $slot);
            if (count($parts) >= 4) {
            $date = Carbon::createFromFormat('Y-m-d', $parts[0] . '-' . $parts[1] . '-' . $parts[2]);
            $time = $parts[3];
            $slotType = $this->getSlotType($slot);
            }
            @endphp
            @if(isset($date) && isset($time))
            <span @class([ 'inline-flex items-center rounded-full font-medium transition-all duration-300 hover:scale-105' , 'px-2 py-1 text-xs'=> $compactView,
                'px-4 py-2 text-sm' => !$compactView,
                'bg-gradient-to-r from-green-100 to-green-200 text-green-800 border border-green-300' => $slotType === 'free',
                'bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 border border-purple-300' => $slotType !== 'free'
                ])>
                @if($slotType === 'free') 🆓 @else ⭐ @endif
                {{ $date->format('M j') }} @if(!$compactView) at @endif {{ $time }}
                <button
                    @class([ 'ml-2 transition-transform duration-200 hover:scale-110' , 'ml-1'=> $compactView,
                    'text-green-600 hover:text-green-800' => $slotType === 'free',
                    'text-purple-600 hover:text-purple-800' => $slotType !== 'free'
                    ])
                    wire:click="toggleTimeSlot('{{ $slot }}')">
                    ✕
                </button>
            </span>
            @endif
            @endforeach
        </div>
    </div>
    @endif

    <!-- Compact View Legend -->
    @if($compactView)
    <div class="mb-4 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 p-3 text-xs">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1">
                <span class="font-bold text-green-700">F</span>
                <span>Free</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="font-bold text-purple-700">P</span>
                <span>Premium</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="text-lg">✓</span>
                <span>Selected</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="text-lg">●</span>
                <span>Booked</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="text-lg">⏳</span>
                <span>Pending</span>
            </div>
            <div class="flex items-center gap-1">
                <span>🔒</span>
                <span>Locked</span>
            </div>
        </div>
    </div>
    @endif

    <!-- Legend -->
    @if(!$compactView)
    <div class="mb-8 flex flex-wrap items-center gap-6 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 p-6 text-sm">
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded border-l-4 border-red-400 bg-red-100"></div>
            <span class="font-medium">Booked</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded border-l-4 border-yellow-400 bg-yellow-100"></div>
            <span class="font-medium">Pending</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded border-l-4 border-green-500 bg-green-100"></div>
            <span class="font-medium">🆓 Free Selected</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded border-l-4 border-purple-500 bg-purple-100"></div>
            <span class="font-medium">⭐ Premium Selected</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded bg-gray-100"></div>
            <span class="font-medium">🔒 Locked/Past</span>
        </div>
        <div class="ml-auto max-w-md text-xs italic text-gray-600">
            *💡 After 6pm additional charges apply for court lights
        </div>
    </div>
    @endif

    <!-- Confirm Button -->
    <div class="flex justify-end">
        <button
            wire:click="confirmBooking"
            @disabled(count($selectedSlots)===0 || $quotaWarning)
            @class([ 'transform rounded-xl font-bold shadow-lg transition-all duration-500 hover:scale-105' , 'px-4 py-2 text-xs'=> $compactView,
            'px-8 py-4 text-sm' => !$compactView,
            'bg-gray-300 text-gray-500 cursor-not-allowed' => count($selectedSlots) === 0,
            'bg-orange-400 text-white cursor-not-allowed' => $quotaWarning,
            'bg-gradient-to-r from-gray-700 via-gray-800 to-gray-900 text-white hover:from-gray-800 hover:via-gray-900 hover:to-black cursor-pointer hover:shadow-xl' => !$quotaWarning && count($selectedSlots) > 0
            ])>
            @if($quotaWarning)
            ⚠️ @if($compactView) QUOTA @else QUOTA EXCEEDED @endif
            @else
            🎾 @if($compactView) BOOK @else CONFIRM @endif
            @if(!$compactView)
            @if($bookingType === 'mixed') MIXED @else {{ strtoupper($bookingType) }} @endif
            BOOKING(S)
            @endif
            @if(count($selectedSlots) > 0) ({{ count($selectedSlots) }}) @endif
            @endif
        </button>
    </div>
</div>

<!-- Time Selector Modal for Monthly View -->
@if($showTimeSelector)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
    <div @class([ 'w-full transform rounded-xl bg-white shadow-2xl' , 'max-w-lg'=> $compactView,
        'max-w-2xl' => !$compactView
        ])>
        <!-- Header -->
        <div class="border-b border-gray-200 bg-gray-50 p-4 rounded-t-xl">
            <div class="flex items-center justify-between">
                <h3 @class([ 'font-bold text-gray-800' , 'text-sm'=> $compactView,
                    'text-lg' => !$compactView
                    ])>
                    🕐 Select Time for {{ Carbon::parse($selectedDateForTime)->format($compactView ? 'M j, Y' : 'l, F j, Y') }}
                </h3>
                <button
                    wire:click="closeTimeSelector"
                    class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            @php $dayInfo = $this->getDateBookingInfo(Carbon::parse($selectedDateForTime)); @endphp
            <div class="mt-2 flex items-center gap-2">
                @if($dayInfo['can_book_free'])
                <span @class([ 'rounded-full bg-green-200 text-green-700' , 'px-2 py-1 text-xs'=> !$compactView,
                    'px-1 text-xs' => $compactView
                    ])>🆓 @if(!$compactView) Free Booking Available @endif</span>
                @endif
                @if($dayInfo['can_book_premium'])
                <span @class([ 'rounded-full bg-purple-200 text-purple-700' , 'px-2 py-1 text-xs'=> !$compactView,
                    'px-1 text-xs' => $compactView
                    ])>⭐ @if(!$compactView) Premium Booking Available @endif</span>
                @endif
            </div>
        </div>

        <!-- Time Slots Grid -->
        <div @class([ 'max-h-96 overflow-y-auto' , 'p-2'=> $compactView,
            'p-4' => !$compactView
            ])>
            <div @class([ 'grid gap-2' , 'grid-cols-3'=> $compactView,
                'sm:grid-cols-2 lg:grid-cols-3' => !$compactView
                ])>
                @foreach($availableTimesForDate as $timeSlot)
                <div
                    @class([ 'rounded-lg border text-center transition-all duration-200' , 'p-2'=> $compactView,
                    'p-3' => !$compactView,
                    'bg-gray-100 text-gray-400' => $timeSlot['is_past'] && !$timeSlot['is_booked'],
                    'bg-red-100 text-red-800 border-red-300' => $timeSlot['is_booked'],
                    'bg-green-100 text-green-800 border-green-300 cursor-pointer hover:bg-green-200' => $timeSlot['is_available'] && $timeSlot['slot_type'] === 'free' && !$timeSlot['is_selected'],
                    'bg-purple-100 text-purple-800 border-purple-300 cursor-pointer hover:bg-purple-200' => $timeSlot['is_available'] && $timeSlot['slot_type'] === 'premium' && !$timeSlot['is_selected'],
                    'bg-green-200 text-green-900 border-green-400 shadow-inner' => $timeSlot['is_selected'] && $timeSlot['slot_type'] === 'free',
                    'bg-purple-200 text-purple-900 border-purple-400 shadow-inner' => $timeSlot['is_selected'] && $timeSlot['slot_type'] === 'premium',
                    ])
                    @if($timeSlot['is_available'])
                    wire:click="toggleTimeSlot('{{ $timeSlot['slot_key'] }}')"
                    @endif>
                    <div @class([ 'font-semibold' , 'text-xs'=> $compactView,
                        '' => !$compactView
                        ])>{{ $timeSlot['start_time'] }}@if(!$compactView) - {{ $timeSlot['end_time'] }}@endif</div>
                    @if($timeSlot['is_past'])
                    <div class="text-xs">@if($compactView) - @else Past @endif</div>
                    @elseif($timeSlot['is_booked'])
                    <div class="text-xs">Booked</div>
                    @elseif($timeSlot['is_selected'])
                    <div class="text-xs">✓ @if(!$compactView) Selected @endif</div>
                    @elseif($timeSlot['is_available'])
                    <div class="text-xs">{{ $timeSlot['slot_type'] === 'free' ? '🆓' : '⭐' }} @if(!$compactView){{ $timeSlot['slot_type'] === 'free' ? ' Free' : ' Premium' }}@endif</div>
                    @if($timeSlot['is_peak'] && !$compactView)
                    <div class="text-xs text-orange-600">💡 Lights required</div>
                    @endif
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-200 p-4 rounded-b-xl">
            <div class="flex justify-between items-center">
                @if(!$compactView)
                <div class="text-sm text-gray-600">
                    Click on available time slots to select them for booking
                </div>
                @endif
                <button
                    wire:click="closeTimeSelector"
                    @class([ 'bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors' , 'px-3 py-1 text-sm'=> $compactView,
                    'px-4 py-2' => !$compactView
                    ])>
                    Done
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Enhanced Date Picker Modal -->
@if($showDatePicker)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
    <div @class([ 'w-full transform rounded-xl bg-white shadow-2xl' , 'max-w-lg'=> $compactView,
        'max-w-2xl' => !$compactView
        ])>
        <!-- Header -->
        <div class="border-b border-gray-200 bg-gray-50 p-4 rounded-t-xl">
            <h3 @class([ 'font-bold text-gray-800' , 'text-sm'=> $compactView,
                'text-lg' => !$compactView
                ])>📅 Jump to Date</h3>

            <!-- Date Picker Mode Selector -->
            <div class="mt-3 flex gap-1 rounded-lg bg-white p-1 border">
                <button
                    wire:click="setDatePickerMode('day')"
                    @class([ 'flex-1 rounded-md font-medium transition-all duration-200' , 'px-2 py-1 text-xs'=> $compactView,
                    'px-3 py-2 text-sm' => !$compactView,
                    'bg-blue-500 text-white' => $datePickerMode === 'day',
                    'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'day'
                    ])>
                    📅 Day
                </button>
                <button
                    wire:click="setDatePickerMode('week')"
                    @class([ 'flex-1 rounded-md font-medium transition-all duration-200' , 'px-2 py-1 text-xs'=> $compactView,
                    'px-3 py-2 text-sm' => !$compactView,
                    'bg-blue-500 text-white' => $datePickerMode === 'week',
                    'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'week'
                    ])>
                    📅 Week
                </button>
                <button
                    wire:click="setDatePickerMode('month')"
                    @class([ 'flex-1 rounded-md font-medium transition-all duration-200' , 'px-2 py-1 text-xs'=> $compactView,
                    'px-3 py-2 text-sm' => !$compactView,
                    'bg-blue-500 text-white' => $datePickerMode === 'month',
                    'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'month'
                    ])>
                    📅 Month
                </button>
            </div>
        </div>

        <!-- Month/Year Selectors -->
        <div class="border-b border-gray-200 p-4">
            <div class="flex gap-4">
                <div class="flex-1">
                    <label @class([ 'block font-medium text-gray-700 mb-1' , 'text-xs'=> $compactView,
                        'text-sm' => !$compactView
                        ])>Month</label>
                    <select wire:model.live="selectedMonth" @class([ 'w-full rounded-lg border border-gray-300 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500' , 'px-2 py-1 text-xs'=> $compactView,
                        'px-3 py-2 text-sm' => !$compactView
                        ])>
                        @foreach($availableMonths as $value => $name)
                        <option value="{{ $value }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label @class([ 'block font-medium text-gray-700 mb-1' , 'text-xs'=> $compactView,
                        'text-sm' => !$compactView
                        ])>Year</label>
                    <select wire:model.live="selectedYear" @class([ 'w-full rounded-lg border border-gray-300 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500' , 'px-2 py-1 text-xs'=> $compactView,
                        'px-3 py-2 text-sm' => !$compactView
                        ])>
                        @foreach($availableYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Calendar Content -->
        <div @class([ 'max-h-96 overflow-y-auto' , 'p-2'=> $compactView,
            'p-4' => !$compactView
            ])>
            @if($datePickerMode === 'day')
            <!-- Day Picker -->
            <div class="grid grid-cols-7 gap-1 mb-2">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                <div @class([ 'text-center font-medium text-gray-500' , 'p-1 text-xs'=> $compactView,
                    'p-2 text-xs' => !$compactView
                    ])>{{ $compactView ? substr($dayName, 0, 1) : $dayName }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-1">
                @foreach($calendarDays as $day)
                <button
                    wire:click="selectDate('{{ $day['date'] }}')"
                    @class([ 'aspect-square rounded-lg transition-all duration-200 hover:scale-105' , 'p-1 text-xs'=> $compactView,
                    'p-2 text-sm' => !$compactView,
                    'text-gray-900 hover:bg-blue-50 border border-transparent hover:border-blue-200' => $day['is_current_month'] && !$day['is_past'] && !$day['is_today'] && $day['booking_type'] === 'none',
                    'text-gray-400 bg-gray-50' => !$day['is_current_month'] || $day['is_past'],
                    'bg-blue-500 text-white font-bold' => $day['is_today'],
                    'bg-green-100 text-green-800 border border-green-300 hover:bg-green-200' => $day['can_book_free'] && !$day['is_today'],
                    'bg-purple-100 text-purple-800 border border-purple-300 hover:bg-purple-200' => $day['can_book_premium'] && !$day['is_today'] && !$day['can_book_free'],
                    'cursor-pointer' => $day['is_current_month'],
                    'cursor-not-allowed' => !$day['is_current_month']
                    ])
                    @disabled(!$day['is_current_month'])
                    title="{{ $day['formatted_date'] }} - {{ $day['booking_type'] === 'free' ? 'Free Booking' : ($day['booking_type'] === 'premium' ? 'Premium Booking' : 'Not Available') }}">
                    <div class="font-medium">{{ $day['day_number'] }}</div>
                    @if($day['can_book_free'])
                    <div class="text-xs">🆓</div>
                    @elseif($day['can_book_premium'])
                    <div class="text-xs">⭐</div>
                    @endif
                </button>
                @endforeach
            </div>

            @elseif($datePickerMode === 'week')
            <!-- Week Picker -->
            <div class="space-y-2">
                @foreach($calendarWeeks as $week)
                <button
                    wire:click="selectWeek('{{ $week['week_start'] }}')"
                    @class([ 'w-full rounded-lg border text-left transition-all duration-200 hover:scale-105' , 'p-2'=> $compactView,
                    'p-3' => !$compactView,
                    'bg-blue-100 border-blue-300 text-blue-800' => $week['is_current_week'],
                    'bg-gray-100 border-gray-300 text-gray-500' => $week['is_past_week'],
                    'bg-green-100 border-green-300 text-green-800 hover:bg-green-200' => $week['can_book_free'] && !$week['is_current_week'] && !$week['is_past_week'],
                    'bg-purple-100 border-purple-300 text-purple-800 hover:bg-purple-200' => $week['can_book_premium'] && !$week['can_book_free'] && !$week['is_current_week'] && !$week['is_past_week'],
                    'bg-white border-gray-300 hover:bg-gray-50' => !$week['is_bookable'] && !$week['is_current_week'] && !$week['is_past_week']
                    ])>
                    <div class="flex items-center justify-between">
                        <div>
                            <div @class([ 'font-medium' , 'text-sm'=> $compactView,
                                '' => !$compactView
                                ])>Week {{ $week['week_number'] }}</div>
                            <div @class([ 'opacity-75' , 'text-xs'=> $compactView,
                                'text-sm' => !$compactView
                                ])>{{ $week['formatted_range'] }}</div>
                        </div>
                        <div class="text-right">
                            @if($week['is_current_week'])
                            <span @class([ 'bg-blue-200 px-2 py-1 rounded' , 'text-xs'=> $compactView || !$compactView
                                ])>Current</span>
                            @elseif($week['is_past_week'])
                            <span @class([ 'bg-gray-200 px-2 py-1 rounded' , 'text-xs'=> $compactView || !$compactView
                                ])>Past</span>
                            @elseif($week['can_book_free'])
                            <span @class([ 'bg-green-200 px-2 py-1 rounded' , 'text-xs'=> $compactView || !$compactView
                                ])>🆓 Free</span>
                            @elseif($week['can_book_premium'])
                            <span @class([ 'bg-purple-200 px-2 py-1 rounded' , 'text-xs'=> $compactView || !$compactView
                                ])>⭐ Premium</span>
                            @else
                            <span @class([ 'bg-gray-200 px-2 py-1 rounded' , 'text-xs'=> $compactView || !$compactView
                                ])>🔒 Locked</span>
                            @endif
                        </div>
                    </div>
                </button>
                @endforeach
            </div>

            @else
            <!-- Month Picker -->
            <div @class([ 'grid gap-3' , 'grid-cols-2'=> $compactView,
                'grid-cols-3' => !$compactView
                ])>
                @foreach($calendarMonths as $month)
                <button
                    wire:click="selectMonth('{{ $month['month_start'] }}')"
                    @class([ 'rounded-lg border text-center transition-all duration-200 hover:scale-105' , 'p-2'=> $compactView,
                    'p-4' => !$compactView,
                    'bg-blue-100 border-blue-300 text-blue-800' => $month['is_current_month'],
                    'bg-gray-100 border-gray-300 text-gray-500' => $month['is_past_month'],
                    'bg-green-100 border-green-300 text-green-800 hover:bg-green-200' => $month['can_book_free'] && !$month['is_current_month'] && !$month['is_past_month'],
                    'bg-purple-100 border-purple-300 text-purple-800 hover:bg-purple-200' => $month['can_book_premium'] && !$month['can_book_free'] && !$month['is_current_month'] && !$month['is_past_month'],
                    'bg-white border-gray-300 hover:bg-gray-50' => !$month['is_bookable'] && !$month['is_current_month'] && !$month['is_past_month']
                    ])>
                    <div @class([ 'font-medium' , 'text-sm'=> $compactView,
                        '' => !$compactView
                        ])>{{ $month['month_name'] }}</div>
                    <div @class([ 'mt-1' , 'text-xs'=> $compactView || !$compactView
                        ])>
                        @if($month['is_current_month'])
                        Current
                        @elseif($month['is_past_month'])
                        Past
                        @elseif($month['booking_type'] === 'mixed')
                        🆓⭐ @if(!$compactView) Mixed @endif
                        @elseif($month['can_book_free'])
                        🆓 @if(!$compactView) Free @endif
                        @elseif($month['can_book_premium'])
                        ⭐ @if(!$compactView) Premium @endif
                        @else
                        🔒 @if(!$compactView) Locked @endif
                        @endif
                    </div>
                </button>
                @endforeach
            </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-200 p-4 rounded-b-xl">
            <div class="flex justify-between items-center">
                @if(!$compactView)
                <div class="text-xs text-gray-500">
                    <div class="flex items-center gap-4">
                        <span class="flex items-center gap-1">
                            <div class="w-3 h-3 bg-green-100 border border-green-300 rounded"></div>
                            🆓 Free
                        </span>
                        <span class="flex items-center gap-1">
                            <div class="w-3 h-3 bg-purple-100 border border-purple-300 rounded"></div>
                            ⭐ Premium
                        </span>
                        <span class="flex items-center gap-1">
                            <div class="w-3 h-3 bg-gray-100 rounded"></div>
                            🔒 Locked
                        </span>
                    </div>
                </div>
                @endif
                <button
                    wire:click="closeDatePicker"
                    @class([ 'text-gray-600 hover:text-gray-800 transition-colors' , 'px-3 py-1 text-sm'=> $compactView,
                    'px-4 py-2' => !$compactView
                    ])>
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Login Reminder Modal -->
@if($showLoginReminder)
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div @class([ 'mx-4 w-full transform rounded-xl bg-white shadow-2xl' , 'max-w-sm p-4'=> $compactView,
        'max-w-md p-6' => !$compactView
        ])>
        <h3 @class([ 'mb-4 font-bold' , 'text-base'=> $compactView,
            'text-lg' => !$compactView
            ])>🔐 Login Required</h3>
        <p @class([ 'mb-6 text-gray-600' , 'text-sm'=> $compactView,
            '' => !$compactView
            ])>Please log in to your tenant account to proceed with booking.</p>
        <div class="flex justify-end gap-3">
            <button
                @class([ 'text-gray-600 transition-colors hover:text-gray-800' , 'px-3 py-1 text-sm'=> $compactView,
                'px-4 py-2' => !$compactView
                ])
                wire:click="closeModal">
                Cancel
            </button>
            <button
                @class([ 'rounded-lg bg-blue-600 text-white transition-colors hover:bg-blue-700' , 'px-3 py-1 text-sm'=> $compactView,
                'px-4 py-2' => !$compactView
                ])
                wire:click="redirectToLogin">
                🔑 Login
            </button>
        </div>
    </div>
</div>
@endif
</div>
