<?php

use Carbon\Carbon;
use App\Models\Booking;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.frontend.app')] class extends Component {
    // View modes
    public string $viewMode = 'weekly';
    public int $courtNumber = 2;

    // Date navigation
    public ?Carbon $currentDate = null;
    public ?Carbon $currentWeekStart = null;
    public ?Carbon $currentMonthStart = null;

    // Date picker
    public bool $showDatePicker = false;
    public string $datePickerMode = 'day'; // day, week, month
    public string $selectedYear = '';
    public string $selectedMonth = '';
    public array $availableYears = [];
    public array $availableMonths = [];
    public array $calendarDays = [];
    public array $calendarWeeks = [];
    public array $calendarMonths = [];

    // Display data
    public array $weekDays = [];
    public array $monthDays = [];
    public array $timeSlots = [];
    public array $bookedSlots = [];
    public array $preliminaryBookedSlots = [];
    public array $selectedSlots = [];

    // Monthly view time selection
    public bool $showTimeSelector = false;
    public string $selectedDateForTime = '';
    public array $availableTimesForDate = [];

    // Booking state
    public string $bookingType = 'free';
    public array $quotaInfo = [];
    public string $quotaWarning = '';
    public bool $isLoggedIn = false;
    public array $pendingBookingData = [];
    public string $bookingReference = '';

    // Modals
    public bool $showConfirmModal = false;
    public bool $showThankYouModal = false;
    public bool $showLoginReminder = false;

    // Premium booking settings
    public ?Carbon $premiumBookingDate = null;
    public bool $isPremiumBookingOpen = false;
    public int $premiumBookingDay = 25; // Configurable

    // Navigation state
    public bool $canGoBack = true;
    public bool $canGoForward = true;

    public function mount()
    {
        $this->isLoggedIn = auth('tenant')->check();
        $this->currentDate = Carbon::today();
        $this->currentWeekStart = Carbon::today()->startOfWeek();
        $this->currentMonthStart = Carbon::today()->startOfMonth();

        // Load premium booking day from settings
        $this->loadPremiumBookingSettings();

        // Initialize date picker
        $this->initializeDatePicker();

        // Check if premium booking is open
        $this->setPremiumBookingDate();

        $this->updateViewData();
        $this->loadQuotaInfo();
        $this->restoreSelectedSlots();
    }

    private function loadPremiumBookingSettings()
    {
        // Load from settings table or use default
        // $this->premiumBookingDay = Setting::where('key', 'premium_booking_day')->value('value') ?? 25;
        $this->premiumBookingDay = 25;
    }

    private function initializeDatePicker()
    {
        $this->selectedYear = $this->currentDate->format('Y');
        $this->selectedMonth = $this->currentDate->format('m');

        // Generate available years (current year to future 2 years)
        $currentYear = Carbon::now()->year;
        $this->availableYears = [];
        for ($year = $currentYear - 1; $year <= $currentYear + 2; $year++) {
            $this->availableYears[] = (string) $year;
        }

        // Generate available months
        $this->availableMonths = [
            '01' => 'January',
            '02' => 'February',
            '03' => 'March',
            '04' => 'April',
            '05' => 'May',
            '06' => 'June',
            '07' => 'July',
            '08' => 'August',
            '09' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December'
        ];

        $this->generateDatePickerData();
    }

    private function generateDatePickerData()
    {
        $this->generateCalendarDays();
        $this->generateCalendarWeeks();
        $this->generateCalendarMonths();
    }

    private function generateCalendarDays()
    {
        $this->calendarDays = [];
        $targetDate = Carbon::createFromFormat('Y-m', $this->selectedYear . '-' . $this->selectedMonth);
        $monthStart = $targetDate->copy()->startOfMonth();
        $monthEnd = $targetDate->copy()->endOfMonth();

        $calendarStart = $monthStart->copy()->startOfWeek();
        $calendarEnd = $monthEnd->copy()->endOfWeek();

        $currentDate = $calendarStart->copy();
        while ($currentDate->lessThanOrEqualTo($calendarEnd)) {
            $isCurrentMonth = $currentDate->month === $monthStart->month;
            $bookingInfo = $this->getDateBookingInfo($currentDate);

            $this->calendarDays[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_number' => $currentDate->format('j'),
                'is_current_month' => $isCurrentMonth,
                'is_today' => $currentDate->isToday(),
                'is_past' => $currentDate->isPast() && !$currentDate->isToday(),
                'formatted_date' => $currentDate->format('M j, Y'),
                'week_start' => $currentDate->isMonday(),
            ] + $bookingInfo;

            $currentDate->addDay();
        }
    }

    private function generateCalendarWeeks()
    {
        $this->calendarWeeks = [];
        $targetDate = Carbon::createFromFormat('Y-m', $this->selectedYear . '-' . $this->selectedMonth);
        $monthStart = $targetDate->copy()->startOfMonth();

        // Get all weeks that intersect with this month
        $weekStart = $monthStart->copy()->startOfWeek();
        $monthEnd = $targetDate->copy()->endOfMonth();

        $currentWeek = $weekStart->copy();
        $weekNumber = 1;

        while ($currentWeek->lessThanOrEqualTo($monthEnd)) {
            $weekEnd = $currentWeek->copy()->endOfWeek();
            $bookingInfo = $this->getWeekBookingInfo($currentWeek);

            $this->calendarWeeks[] = [
                'week_start' => $currentWeek->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'week_number' => $weekNumber,
                'formatted_range' => $currentWeek->format('M j') . ' - ' . $weekEnd->format('M j'),
                'is_current_week' => $currentWeek->isSameWeek(Carbon::today()),
                'is_past_week' => $weekEnd->isPast(),
            ] + $bookingInfo;

            $currentWeek->addWeek();
            $weekNumber++;
        }
    }

    private function generateCalendarMonths()
    {
        $this->calendarMonths = [];
        $currentYear = (int) $this->selectedYear;

        for ($month = 1; $month <= 12; $month++) {
            $monthDate = Carbon::create($currentYear, $month, 1);
            $bookingInfo = $this->getMonthBookingInfo($monthDate);

            $this->calendarMonths[] = [
                'month_start' => $monthDate->format('Y-m-d'),
                'month_number' => $month,
                'month_name' => $monthDate->format('F'),
                'is_current_month' => $monthDate->isSameMonth(Carbon::today()),
                'is_past_month' => $monthDate->endOfMonth()->isPast(),
            ] + $bookingInfo;
        }
    }

    private function getDateBookingInfo($date)
    {
        $today = Carbon::today();
        $nextWeekStart = $today->copy()->addWeek()->startOfWeek();
        $nextWeekEnd = $nextWeekStart->copy()->endOfWeek();

        // Free booking: next week only
        $canBookFree = $date->between($nextWeekStart, $nextWeekEnd);

        // Premium booking: after next week, but only when premium booking is open
        $canBookPremium = $date->greaterThan($nextWeekEnd) &&
            $date->lessThanOrEqualTo($today->copy()->addMonths(3)) &&
            $this->isPremiumBookingOpen;

        return [
            'can_book_free' => $canBookFree,
            'can_book_premium' => $canBookPremium,
            'is_bookable' => $canBookFree || $canBookPremium,
            'booking_type' => $canBookFree ? 'free' : ($canBookPremium ? 'premium' : 'none'),
        ];
    }

    private function getWeekBookingInfo($weekStart)
    {
        $today = Carbon::today();
        $nextWeekStart = $today->copy()->addWeek()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        // Check if this week overlaps with free booking period (next week)
        $canBookFree = $weekStart->between($nextWeekStart, $nextWeekStart->copy()->endOfWeek()) ||
            $weekEnd->between($nextWeekStart, $nextWeekStart->copy()->endOfWeek());

        // Check if this week is in premium booking period
        $canBookPremium = $weekStart->greaterThan($nextWeekStart->copy()->endOfWeek()) &&
            $weekStart->lessThanOrEqualTo($today->copy()->addMonths(3)) &&
            $this->isPremiumBookingOpen;

        return [
            'can_book_free' => $canBookFree,
            'can_book_premium' => $canBookPremium,
            'is_bookable' => $canBookFree || $canBookPremium,
            'booking_type' => $canBookFree ? 'free' : ($canBookPremium ? 'premium' : 'none'),
        ];
    }

    private function getMonthBookingInfo($monthStart)
    {
        $today = Carbon::today();
        $nextWeekStart = $today->copy()->addWeek()->startOfWeek();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Check if month contains free booking days
        $canBookFree = $monthStart->lessThanOrEqualTo($nextWeekStart->copy()->endOfWeek()) &&
            $monthEnd->greaterThanOrEqualTo($nextWeekStart);

        // Check if month contains premium booking days
        $canBookPremium = $monthStart->lessThanOrEqualTo($today->copy()->addMonths(3)) &&
            $monthEnd->greaterThan($nextWeekStart->copy()->endOfWeek()) &&
            $this->isPremiumBookingOpen;

        return [
            'can_book_free' => $canBookFree,
            'can_book_premium' => $canBookPremium,
            'is_bookable' => $canBookFree || $canBookPremium,
            'booking_type' => $canBookFree && $canBookPremium ? 'mixed' : ($canBookFree ? 'free' : ($canBookPremium ? 'premium' : 'none')),
        ];
    }

    public function updatedSelectedYear()
    {
        $this->generateDatePickerData();
    }

    public function updatedSelectedMonth()
    {
        $this->generateDatePickerData();
    }

    public function setDatePickerMode($mode)
    {
        $this->datePickerMode = $mode;
        $this->generateDatePickerData();
    }

    public function showDatePicker()
    {
        $this->showDatePicker = true;
        $this->generateDatePickerData();
    }

    public function selectDate($date)
    {
        $selectedDate = Carbon::parse($date);
        $this->navigateToDate($selectedDate);
        $this->showDatePicker = false;
    }

    public function selectWeek($weekStart)
    {
        $selectedDate = Carbon::parse($weekStart);
        $this->navigateToDate($selectedDate);
        $this->showDatePicker = false;
    }

    public function selectMonth($monthStart)
    {
        $selectedDate = Carbon::parse($monthStart);
        $this->navigateToDate($selectedDate);
        $this->showDatePicker = false;
    }

    private function navigateToDate($selectedDate)
    {
        $this->storeSelectedSlots();

        $this->currentDate = $selectedDate;
        $this->currentWeekStart = $selectedDate->copy()->startOfWeek();
        $this->currentMonthStart = $selectedDate->copy()->startOfMonth();

        $this->selectedYear = $selectedDate->format('Y');
        $this->selectedMonth = $selectedDate->format('m');

        $this->updateViewData();
        $this->restoreSelectedSlots();
    }

    // Monthly view - show time selector for a specific date
    public function showTimesForDate($date)
    {
        if (!$this->isLoggedIn) {
            $this->storeSelectedSlots();
            $this->showLoginReminder = true;
            return;
        }

        $selectedDate = Carbon::parse($date);

        // Check if date is bookable
        if (!$this->canBookSlot($selectedDate)) {
            return;
        }

        $this->selectedDateForTime = $date;
        $this->generateAvailableTimesForDate($selectedDate);
        $this->showTimeSelector = true;
    }

    private function generateAvailableTimesForDate($date)
    {
        $this->availableTimesForDate = [];

        // Get booked slots for this specific date
        $bookedSlotsForDate = Booking::where('court_id', $this->courtNumber)
            ->where('date', $date->format('Y-m-d'))
            ->where('status', '!=', 'cancelled')
            ->get()
            ->pluck('start_time')
            ->map(function ($time) {
                return $time->format('H:i');
            })
            ->toArray();

        foreach ($this->timeSlots as $slot) {
            $slotKey = $date->format('Y-m-d') . '-' . $slot['start'];
            $slotDateTime = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $slot['start']);

            $isBooked = in_array($slot['start'], $bookedSlotsForDate);
            $isPast = $slotDateTime->isPast();
            $isSelected = in_array($slotKey, $this->selectedSlots);

            $this->availableTimesForDate[] = [
                'slot_key' => $slotKey,
                'start_time' => $slot['start'],
                'end_time' => $slot['end'],
                'is_peak' => $slot['is_peak'],
                'is_booked' => $isBooked,
                'is_past' => $isPast,
                'is_selected' => $isSelected,
                'is_available' => !$isBooked && !$isPast,
                'slot_type' => $this->getSlotType($slotKey),
            ];
        }
    }

    public function closeTimeSelector()
    {
        $this->showTimeSelector = false;
        $this->selectedDateForTime = '';
        $this->availableTimesForDate = [];
    }

    private function setPremiumBookingDate()
    {
        $today = Carbon::today();
        $targetDate = $today->copy()->day($this->premiumBookingDay);

        // If target day is in the past this month, move to next month
        if ($targetDate->isPast()) {
            $targetDate->addMonth();
        }

        // If target day is weekend or holiday, move to nearest weekday
        while ($targetDate->isWeekend()) {
            if ($targetDate->isSaturday()) {
                $targetDate->subDay(); // Friday
            } else {
                $targetDate->addDay(); // Monday
            }
        }

        $this->premiumBookingDate = $targetDate;
        $this->isPremiumBookingOpen = $today->isSameDay($targetDate);
    }

    public function switchView($mode)
    {
        $this->storeSelectedSlots();
        $this->viewMode = $mode;
        $this->closeTimeSelector(); // Close time selector when switching views
        $this->updateViewData();
        $this->restoreSelectedSlots();
    }

    private function storeSelectedSlots()
    {
        if (!empty($this->selectedSlots)) {
            session(['booking_selected_slots' => $this->selectedSlots]);
        }
    }

    private function restoreSelectedSlots()
    {
        if (session()->has('booking_selected_slots')) {
            $this->selectedSlots = session('booking_selected_slots');
            $this->validateSelections();
        }
    }

    public function updateViewData()
    {
        switch ($this->viewMode) {
            case 'weekly':
                $this->generateWeekView();
                break;
            case 'monthly':
                $this->generateMonthView();
                break;
            case 'daily':
                $this->generateDayView();
                break;
        }

        $this->generateTimeSlots();
        $this->loadBookedSlots();
        $this->updateNavigationState();
        $this->validateSelections();
    }

    public function loadBookedSlots()
    {
        $startDate = null;
        $endDate = null;

        switch ($this->viewMode) {
            case 'weekly':
                $startDate = $this->currentWeekStart;
                $endDate = $this->currentWeekStart->copy()->addDays(6);
                break;
            case 'monthly':
                $startDate = $this->currentMonthStart;
                $endDate = $this->currentMonthStart->copy()->endOfMonth();
                break;
            case 'daily':
                $startDate = $this->currentDate;
                $endDate = $this->currentDate->copy();
                break;
        }

        if (!$startDate || !$endDate) {
            $this->bookedSlots = [];
            $this->preliminaryBookedSlots = [];
            return;
        }

        $bookings = Booking::where('court_id', $this->courtNumber)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('date', [$startDate, $endDate])
            ->with('tenant')
            ->get();

        $this->bookedSlots = [];
        $this->preliminaryBookedSlots = [];

        foreach ($bookings as $booking) {
            $slotKey = $booking->date->format('Y-m-d') . '-' . $booking->start_time->format('H:i');

            $slotData = [
                'key' => $slotKey,
                'type' => $booking->booking_type,
                'tenant_id' => $booking->tenant_id,
                'tenant_name' => $booking->tenant->name ?? 'Unknown',
                'is_own_booking' => $this->isLoggedIn && $booking->tenant_id === auth('tenant')->id(),
            ];

            if ($booking->status === 'confirmed') {
                $this->bookedSlots[] = $slotData;
            } else {
                $this->preliminaryBookedSlots[] = $slotData;
            }
        }
    }

    private function generateWeekView()
    {
        $this->weekDays = [];
        $startDate = $this->currentWeekStart->copy();

        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $bookingInfo = $this->getDateBookingInfo($currentDate);

            $this->weekDays[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_name' => $currentDate->format('D'),
                'day_number' => $currentDate->format('j'),
                'month_name' => $currentDate->format('M'),
                'is_today' => $currentDate->isToday(),
                'is_past' => $currentDate->isPast() && !$currentDate->isToday(),
                'formatted_date' => $currentDate->format('D, M j'),
            ] + $bookingInfo;
        }
    }

    private function generateMonthView()
    {
        $this->monthDays = [];
        $monthStart = $this->currentMonthStart->copy()->startOfWeek();
        $monthEnd = $this->currentMonthStart->copy()->endOfMonth()->endOfWeek();

        $currentDate = $monthStart->copy();
        while ($currentDate->lessThanOrEqualTo($monthEnd)) {
            $isCurrentMonth = $currentDate->month === $this->currentMonthStart->month;
            $bookingInfo = $this->getDateBookingInfo($currentDate);

            $this->monthDays[] = [
                'date' => $currentDate->format('Y-m-d'),
                'day_number' => $currentDate->format('j'),
                'is_current_month' => $isCurrentMonth,
                'is_today' => $currentDate->isToday(),
                'is_past' => $currentDate->isPast() && !$currentDate->isToday(),
                'week_start' => $currentDate->isMonday(),
            ] + $bookingInfo;

            $currentDate->addDay();
        }
    }

    private function generateDayView()
    {
        $this->generateTimeSlots();
    }

    private function generateTimeSlots()
    {
        $this->timeSlots = [];
        for ($hour = 8; $hour < 23; $hour++) {
            $this->timeSlots[] = [
                'start' => sprintf('%02d:00', $hour),
                'end' => sprintf('%02d:00', $hour + 1),
                'is_peak' => $hour >= 18,
            ];
        }
    }

    public function loadQuotaInfo()
    {
        if (!$this->isLoggedIn) {
            $this->quotaInfo = [];
            return;
        }

        $tenant = auth('tenant')->user();
        $weekStart = Carbon::today()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $weeklyBookingDays = Booking::where('tenant_id', $tenant->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->where('status', '!=', 'cancelled')
            ->get()
            ->groupBy(function ($booking) {
                return $booking->date->format('Y-m-d');
            })
            ->count();

        $remainingQuota = max(0, 3 - $weeklyBookingDays);

        $this->quotaInfo = [
            'weekly_used' => $weeklyBookingDays,
            'weekly_total' => 3,
            'weekly_remaining' => $remainingQuota,
            'combined' => [
                'remaining' => $remainingQuota
            ]
        ];
    }

    public function toggleTimeSlot($slotKey)
    {
        if (!$this->isLoggedIn) {
            $this->storeSelectedSlots();
            $this->showLoginReminder = true;
            return;
        }

        $parts = explode('-', $slotKey);
        if (count($parts) < 4) return;

        $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
        $slotDate = Carbon::parse($date);

        // Don't allow booking past dates
        if ($slotDate->isPast()) {
            return;
        }

        if (!$this->canBookSlot($slotDate)) {
            return;
        }

        if ($this->isSlotBooked($slotKey)) {
            return;
        }

        $slotDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $parts[3]);
        if ($slotDateTime->isPast()) {
            return;
        }

        $index = array_search($slotKey, $this->selectedSlots);
        if ($index !== false) {
            unset($this->selectedSlots[$index]);
            $this->selectedSlots = array_values($this->selectedSlots);
        } else {
            $dailySlots = array_filter($this->selectedSlots, function ($slot) use ($date) {
                return str_starts_with($slot, $date);
            });

            if (count($dailySlots) >= 2) {
                $this->quotaWarning = 'Maximum 2 hours per day allowed.';
                return;
            }

            $this->selectedSlots[] = $slotKey;
        }

        $this->storeSelectedSlots();
        $this->determineBookingType();
        $this->validateSelections();

        // Update available times if time selector is open
        if ($this->showTimeSelector && $this->selectedDateForTime === $date) {
            $this->generateAvailableTimesForDate($slotDate);
        }
    }

    private function canBookSlot($date)
    {
        $today = Carbon::today();
        $nextWeekStart = $today->copy()->addWeek()->startOfWeek();
        $nextWeekEnd = $nextWeekStart->copy()->endOfWeek();

        // Free booking: next week only
        $canBookFree = $date->between($nextWeekStart, $nextWeekEnd);

        // Premium booking: after next week, when premium booking is open
        $canBookPremium = $date->greaterThan($nextWeekEnd) &&
            $date->lessThanOrEqualTo($today->copy()->addMonths(3)) &&
            $this->isPremiumBookingOpen;

        return $canBookFree || $canBookPremium;
    }

    private function isSlotBooked($slotKey)
    {
        $bookedKeys = array_column($this->bookedSlots, 'key');
        $preliminaryKeys = array_column($this->preliminaryBookedSlots, 'key');

        return in_array($slotKey, $bookedKeys) || in_array($slotKey, $preliminaryKeys);
    }

    public function determineBookingType()
    {
        if (empty($this->selectedSlots)) {
            $this->bookingType = 'free';
            return;
        }

        $hasPremium = false;
        $today = Carbon::today();
        $nextWeekEnd = $today->copy()->addWeek()->endOfWeek();

        foreach ($this->selectedSlots as $slotKey) {
            $parts = explode('-', $slotKey);
            if (count($parts) >= 3) {
                $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
                $slotDate = Carbon::parse($date);
                if ($slotDate->greaterThan($nextWeekEnd)) {
                    $hasPremium = true;
                    break;
                }
            }
        }

        $this->bookingType = $hasPremium ? 'mixed' : 'free';
    }

    public function getSlotType($slotKey)
    {
        $parts = explode('-', $slotKey);
        if (count($parts) >= 3) {
            $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
            $slotDate = Carbon::parse($date);
            $today = Carbon::today();
            $nextWeekEnd = $today->copy()->addWeek()->endOfWeek();

            return $slotDate->greaterThan($nextWeekEnd) ? 'premium' : 'free';
        }
        return 'free';
    }

    private function validateSelections()
    {
        if (empty($this->selectedSlots)) {
            $this->quotaWarning = '';
            return;
        }

        $selectedDays = [];
        foreach ($this->selectedSlots as $slot) {
            $parts = explode('-', $slot);
            if (count($parts) >= 3) {
                $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
                $selectedDays[$date] = true;
            }
        }

        $dayCount = count($selectedDays);
        $remaining = $this->quotaInfo['weekly_remaining'] ?? 0;

        if ($dayCount > $remaining) {
            $this->quotaWarning = "You can only book {$remaining} more days this week.";
        } else {
            $this->quotaWarning = '';
        }
    }

    private function updateNavigationState()
    {
        $today = Carbon::today();
        $maxFutureDate = $today->copy()->addMonths(3);

        switch ($this->viewMode) {
            case 'weekly':
                $this->canGoBack = true;
                $this->canGoForward = $this->currentWeekStart->lessThan($maxFutureDate->startOfWeek());
                break;
            case 'monthly':
                $this->canGoBack = true;
                $this->canGoForward = $this->currentMonthStart->lessThan($maxFutureDate->startOfMonth());
                break;
            case 'daily':
                $this->canGoBack = true;
                $this->canGoForward = $this->currentDate->lessThan($maxFutureDate);
                break;
        }
    }

    // Navigation methods
    public function previousPeriod()
    {
        $this->storeSelectedSlots();
        $this->closeTimeSelector();

        switch ($this->viewMode) {
            case 'weekly':
                $this->currentWeekStart->subWeek();
                break;
            case 'monthly':
                $this->currentMonthStart->subMonth();
                break;
            case 'daily':
                $this->currentDate->subDay();
                break;
        }

        $this->updateViewData();
        $this->restoreSelectedSlots();
    }

    public function nextPeriod()
    {
        $this->storeSelectedSlots();
        $this->closeTimeSelector();

        switch ($this->viewMode) {
            case 'weekly':
                $this->currentWeekStart->addWeek();
                break;
            case 'monthly':
                $this->currentMonthStart->addMonth();
                break;
            case 'daily':
                $this->currentDate->addDay();
                break;
        }

        $this->updateViewData();
        $this->restoreSelectedSlots();
    }

    public function goToToday()
    {
        $this->storeSelectedSlots();
        $this->closeTimeSelector();

        $this->currentDate = Carbon::today();
        $this->currentWeekStart = Carbon::today()->startOfWeek();
        $this->currentMonthStart = Carbon::today()->startOfMonth();

        $this->updateViewData();
        $this->restoreSelectedSlots();
    }

    // Booking process methods
    public function confirmBooking()
    {
        if (empty($this->selectedSlots)) return;

        if (!$this->isLoggedIn) {
            $this->storeSelectedSlots();
            $this->showLoginReminder = true;
            return;
        }

        if ($this->quotaWarning) {
            session()->flash('error', $this->quotaWarning);
            return;
        }

        $this->prepareBookingData();
        $this->showConfirmModal = true;
    }

    public function prepareBookingData()
    {
        $this->pendingBookingData = [];

        foreach ($this->selectedSlots as $slotKey) {
            $parts = explode('-', $slotKey);
            if (count($parts) < 4) continue;

            $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
            $time = $parts[3];

            try {
                $dateObj = Carbon::createFromFormat('Y-m-d', $date);
                $timeObj = Carbon::createFromFormat('H:i', $time);

                $isLightRequired = $timeObj->hour >= 18;
                $bookingType = $this->getSlotType($slotKey);

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
    }

    public function processBooking()
    {
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

                if (method_exists($booking, 'calculatePrice')) {
                    $booking->calculatePrice();
                }
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

        $firstBooking = $bookings[0];
        $this->bookingReference = 'C' . $this->courtNumber . '-' .
            $firstBooking->date->format('Y-m-d') . '-' .
            $firstBooking->start_time->format('Hi') . '-' .
            $firstBooking->id;

        foreach ($bookings as $booking) {
            $booking->update(['booking_reference' => $this->bookingReference]);
        }

        $this->selectedSlots = [];
        session()->forget('booking_selected_slots');
        $this->showConfirmModal = false;
        $this->showThankYouModal = true;

        $this->loadBookedSlots();
        $this->loadQuotaInfo();
    }

    public function closeModal()
    {
        $this->showConfirmModal = false;
        $this->showThankYouModal = false;
        $this->showLoginReminder = false;
        $this->showDatePicker = false;
        $this->closeTimeSelector();
    }

    public function redirectToLogin()
    {
        return redirect()->route('login');
    }
}; ?>

<div x-data="courtBooking()" x-init="init()">
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
        <!-- View Mode Selector -->
        <div class="mb-6 flex justify-center">
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
        </div>

        <!-- Navigation Controls -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 p-4 shadow-sm">
            <button
                wire:click="previousPeriod"
                @disabled(!$canGoBack)
                @class([ 'flex items-center gap-2 rounded-lg px-4 py-2 transition-all duration-300' , 'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 cursor-pointer shadow-sm'=> $canGoBack,
                'bg-gray-200 border border-gray-200 text-gray-400 cursor-not-allowed' => !$canGoBack
                ])>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Previous
            </button>

            <div class="flex items-center gap-2">
                <div class="text-center">
                    @if($viewMode === 'weekly')
                    <h3 class="text-lg font-semibold">
                        {{ $currentWeekStart->format('M j') }} - {{ $currentWeekStart->copy()->addDays(6)->format('M j, Y') }}
                    </h3>
                    @elseif($viewMode === 'monthly')
                    <h3 class="text-lg font-semibold">{{ $currentMonthStart->format('F Y') }}</h3>
                    @else
                    <h3 class="text-lg font-semibold">{{ $currentDate->format('l, F j, Y') }}</h3>
                    @endif
                </div>

                <!-- Date Picker Button -->
                <button
                    wire:click="showDatePicker"
                    class="ml-2 rounded-lg bg-purple-100 px-3 py-1 text-purple-700 transition-all duration-300 hover:bg-purple-200">
                    📅 Jump to Date
                </button>
            </div>

            <div class="flex items-center gap-2">
                <button
                    wire:click="goToToday"
                    class="rounded-lg bg-blue-100 px-4 py-2 text-blue-700 transition-all duration-300 hover:bg-blue-200">
                    📅 Today
                </button>

                <button
                    wire:click="nextPeriod"
                    @disabled(!$canGoForward)
                    @class([ 'flex items-center gap-2 rounded-lg px-4 py-2 transition-all duration-300' , 'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 cursor-pointer shadow-sm'=> $canGoForward,
                    'bg-gray-200 border border-gray-200 text-gray-400 cursor-not-allowed' => !$canGoForward
                    ])>
                    Next
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Booking Rules Info -->
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

        <!-- Login Prompt -->
        @if(!$isLoggedIn)
        <div class="mb-6 rounded-r-lg border-l-4 border-blue-400 bg-blue-50 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Login to see your booking quota</strong> and make reservations.
                        <a class="underline transition-colors hover:text-blue-900" href="{{ route('login') }}">Sign in here</a>
                    </p>
                </div>
            </div>
        </div>
        @endif

        <!-- Quota Display -->
        @if($isLoggedIn && !empty($quotaInfo))
        <div class="mb-6 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-blue-800">Weekly Quota</h3>
                    <p class="text-sm text-blue-600">Maximum 3 days per week, 2 hours per day</p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold text-blue-600">
                        {{ $quotaInfo['weekly_used'] ?? 0 }}/{{ $quotaInfo['weekly_total'] ?? 3 }}
                    </div>
                    <div class="text-sm text-blue-600">Days used this week</div>
                </div>
            </div>
            @if(($quotaInfo['weekly_remaining'] ?? 0) > 0)
            <div class="mt-2 text-sm text-green-600">
                ✅ You can book {{ $quotaInfo['weekly_remaining'] }} more days this week
            </div>
            @else
            <div class="mt-2 text-sm text-red-600">
                ⚠️ You have reached your weekly booking limit
            </div>
            @endif
        </div>
        @endif

        <!-- Quota Warning -->
        @if($quotaWarning)
        <div class="mb-6 rounded-r-lg border-l-4 border-orange-400 bg-orange-50 p-4">
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

        <!-- Weekly View -->
        @if($viewMode === 'weekly')
        <div class="mb-8 overflow-x-auto rounded-xl border border-gray-300 shadow-lg">
            <table class="w-full border-collapse bg-white">
                <thead>
                    <tr>
                        <th class="border-r border-gray-300 bg-gray-100 p-4 text-left font-semibold text-gray-700">Time</th>
                        @foreach($weekDays as $day)
                        <th @class([ 'border-r border-gray-300 last:border-r-0 p-4 text-white text-center relative' , 'bg-gradient-to-b from-blue-500 to-blue-600'=> $day['is_today'],
                            'bg-gradient-to-b from-gray-400 to-gray-500' => $day['is_past'] && !$day['is_today'],
                            'bg-gradient-to-b from-green-600 to-green-700' => $day['can_book_free'] && !$day['is_today'] && !$day['is_past'],
                            'bg-gradient-to-b from-purple-600 to-purple-700' => $day['can_book_premium'] && !$day['is_today'] && !$day['can_book_free'] && !$day['is_past'],
                            'bg-gradient-to-b from-gray-300 to-gray-400' => !$day['is_bookable'] && !$day['is_today'] && !$day['is_past']
                            ])>
                            <div class="flex flex-col items-center">
                                <div class="text-sm font-bold">{{ $day['day_name'] }}</div>
                                <div class="text-2xl font-bold">{{ $day['day_number'] }}</div>
                                <div class="text-xs opacity-90">{{ $day['month_name'] }}</div>
                                @if($day['is_today'])
                                <div class="mt-1 rounded-full bg-blue-400 px-2 py-0.5 text-xs">TODAY</div>
                                @elseif($day['is_past'])
                                <div class="mt-1 rounded-full bg-gray-300 px-2 py-0.5 text-xs">PAST</div>
                                @elseif($day['can_book_free'])
                                <div class="mt-1 rounded-full bg-green-400 px-2 py-0.5 text-xs">🆓 FREE</div>
                                @elseif($day['can_book_premium'])
                                <div class="mt-1 rounded-full bg-purple-400 px-2 py-0.5 text-xs">⭐ PREMIUM</div>
                                @else
                                <div class="mt-1 rounded-full bg-gray-300 px-2 py-0.5 text-xs">LOCKED</div>
                                @endif
                            </div>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeSlots as $slot)
                    <tr class="border-b border-gray-200 transition-colors duration-200 last:border-b-0 hover:bg-gray-50">
                        <td class="border-r border-gray-300 bg-gray-50 p-4 font-medium text-gray-700">
                            <div class="text-sm">{{ $slot['start'] }}</div>
                            <div class="text-xs text-gray-500">{{ $slot['end'] }}</div>
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
                            @class([ 'time-slot p-3 text-center transition-all duration-200' , 'bg-gray-100 text-gray-400'=> ($isPastSlot || !$day['is_bookable']) && !$showBookingInfo,
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
                            <div @class([ 'text-xs font-bold' , 'text-green-700'=> $slotType === 'free',
                                'text-purple-700' => $slotType === 'premium'
                                ])>
                                ✓ Selected
                            </div>
                            @elseif($isBooked)
                            <div class="text-xs font-bold text-red-700">
                                @if($bookedSlot['is_own_booking'] ?? false)
                                Your Booking
                                @else
                                Booked
                                @endif
                            </div>
                            @elseif($isPreliminary)
                            <div class="text-xs font-bold text-yellow-700">
                                @if($preliminarySlot['is_own_booking'] ?? false)
                                Your Pending
                                @else
                                Pending
                                @endif
                            </div>
                            @elseif($canBook)
                            <div class="text-xs opacity-60">
                                @if($slotType === 'free') 🆓 Free @else ⭐ Premium @endif
                            </div>
                            @endif

                            @if($slot['is_peak'] && $canBook)
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
                <div class="bg-gray-100 p-4 text-center font-semibold text-gray-700">{{ $dayName }}</div>
                @endforeach

                <!-- Calendar days -->
                @foreach($monthDays as $day)
                <div @class([ 'aspect-square border-r border-b border-gray-200 p-2 transition-all duration-200' , 'bg-white hover:bg-gray-50'=> $day['is_current_month'] && !$day['is_past'],
                    'bg-gray-50 text-gray-400' => !$day['is_current_month'] || $day['is_past'],
                    'bg-blue-100 border-blue-300' => $day['is_today'],
                    'cursor-pointer hover:shadow-md' => $day['is_bookable'] && $day['is_current_month'],
                    ])
                    @if($day['is_bookable'] && $day['is_current_month'])
                    wire:click="showTimesForDate('{{ $day['date'] }}')"
                    @endif>
                    <div class="flex h-full flex-col">
                        <div @class([ 'text-sm font-medium' , 'text-blue-600 font-bold'=> $day['is_today'],
                            'text-gray-900' => $day['is_current_month'] && !$day['is_today'] && !$day['is_past'],
                            'text-gray-400' => !$day['is_current_month'] || $day['is_past']
                            ])>
                            {{ $day['day_number'] }}
                        </div>

                        @if($day['is_past'])
                        <div class="mt-1 text-xs text-gray-400">Past</div>
                        @elseif($day['is_bookable'] && $day['is_current_month'])
                        <div class="mt-1 flex-1 space-y-1">
                            @if($day['can_book_free'])
                            <div class="rounded bg-green-100 px-1 py-0.5 text-xs text-green-700">🆓 Free</div>
                            @endif
                            @if($day['can_book_premium'])
                            <div class="rounded bg-purple-100 px-1 py-0.5 text-xs text-purple-700">⭐ Premium</div>
                            @endif
                            <div class="text-xs text-blue-600 font-medium">Click to book</div>
                        </div>
                        @elseif($day['is_current_month'])
                        <div class="mt-1 text-xs text-gray-400">🔒 Locked</div>
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
            <div class="border-b border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ $currentDate->format('l, F j, Y') }}
                    </h3>
                    <div class="flex items-center gap-2">
                        @php $dayInfo = $this->getDateBookingInfo($currentDate); @endphp
                        @if($currentDate->isPast())
                        <span class="rounded-full bg-gray-200 px-2 py-1 text-xs text-gray-600">Past Date</span>
                        @elseif($dayInfo['can_book_free'])
                        <span class="rounded-full bg-green-200 px-2 py-1 text-xs text-green-700">🆓 Free Booking</span>
                        @elseif($dayInfo['can_book_premium'])
                        <span class="rounded-full bg-purple-200 px-2 py-1 text-xs text-purple-700">⭐ Premium Booking</span>
                        @else
                        <span class="rounded-full bg-gray-200 px-2 py-1 text-xs text-gray-600">🔒 Locked</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3">
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
                    @class([ 'rounded-lg border p-4 text-center transition-all duration-200' , 'bg-gray-100 text-gray-400'=> $isPastSlot && !$isBooked && !$isPreliminary,
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
                    <div class="font-semibold">{{ $slot['start'] }} - {{ $slot['end'] }}</div>
                    @if($isPastSlot && !$isBooked && !$isPreliminary)
                    <div class="text-xs">Past</div>
                    @elseif($isBooked)
                    <div class="text-xs">
                        @if($bookedSlot['is_own_booking'] ?? false)
                        Your Booking
                        @else
                        Booked
                        @endif
                    </div>
                    @elseif($isPreliminary)
                    <div class="text-xs">
                        @if($preliminarySlot['is_own_booking'] ?? false)
                        Your Pending
                        @else
                        Pending
                        @endif
                    </div>
                    @elseif($isSelected)
                    <div class="text-xs">✓ Selected</div>
                    @elseif($canBook)
                    <div class="text-xs">{{ $slotType === 'free' ? '🆓 Free' : '⭐ Premium' }}</div>
                    @if($slot['is_peak'])
                    <div class="text-xs text-orange-600">💡 Lights required</div>
                    @endif
                    @else
                    <div class="text-xs text-gray-400">🔒 Locked</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Selection Summary -->
        @if(count($selectedSlots) > 0)
        <div class="mb-8 rounded-xl border border-green-200 bg-gradient-to-r from-green-50 to-blue-50 p-6 shadow-sm">
            <h4 class="mb-4 flex items-center gap-2 font-bold text-gray-800">
                🎯 Selected Time Slots ({{ count($selectedSlots) }})
                @if($bookingType === 'mixed')
                <span class="rounded-full bg-gradient-to-r from-blue-500 to-purple-500 px-2 py-1 text-xs text-white">Mixed Booking</span>
                @endif
            </h4>
            <div class="flex flex-wrap gap-3">
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
                <span @class([ 'inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-all duration-300 hover:scale-105' , 'bg-gradient-to-r from-green-100 to-green-200 text-green-800 border border-green-300'=> $slotType === 'free',
                    'bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 border border-purple-300' => $slotType !== 'free'
                    ])>
                    @if($slotType === 'free') 🆓 @else ⭐ @endif
                    {{ $date->format('M j') }} at {{ $time }}
                    <button
                        @class([ 'ml-2 transition-transform duration-200 hover:scale-110' , 'text-green-600 hover:text-green-800'=> $slotType === 'free',
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

        <!-- Legend -->
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

        <!-- Confirm Button -->
        <div class="flex justify-end">
            <button
                wire:click="confirmBooking"
                @disabled(count($selectedSlots)===0 || $quotaWarning)
                @class([ 'transform rounded-xl px-8 py-4 text-sm font-bold shadow-lg transition-all duration-500 hover:scale-105' , 'bg-gray-300 text-gray-500 cursor-not-allowed'=> count($selectedSlots) === 0,
                'bg-orange-400 text-white cursor-not-allowed' => $quotaWarning,
                'bg-gradient-to-r from-gray-700 via-gray-800 to-gray-900 text-white hover:from-gray-800 hover:via-gray-900 hover:to-black cursor-pointer hover:shadow-xl' => !$quotaWarning && count($selectedSlots) > 0
                ])>
                @if($quotaWarning)
                ⚠️ QUOTA EXCEEDED
                @else
                🎾 CONFIRM
                @if($bookingType === 'mixed') MIXED @else {{ strtoupper($bookingType) }} @endif
                BOOKING(S)
                @if(count($selectedSlots) > 0) ({{ count($selectedSlots) }}) @endif
                @endif
            </button>
        </div>
    </div>

    <!-- Time Selector Modal for Monthly View -->
    @if($showTimeSelector)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
        <div class="w-full max-w-2xl transform rounded-xl bg-white shadow-2xl">
            <!-- Header -->
            <div class="border-b border-gray-200 bg-gray-50 p-4 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-800">
                        🕐 Select Time for {{ Carbon::parse($selectedDateForTime)->format('l, F j, Y') }}
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
                    <span class="rounded-full bg-green-200 px-2 py-1 text-xs text-green-700">🆓 Free Booking Available</span>
                    @endif
                    @if($dayInfo['can_book_premium'])
                    <span class="rounded-full bg-purple-200 px-2 py-1 text-xs text-purple-700">⭐ Premium Booking Available</span>
                    @endif
                </div>
            </div>

            <!-- Time Slots Grid -->
            <div class="p-4 max-h-96 overflow-y-auto">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($availableTimesForDate as $timeSlot)
                    <div
                        @class([ 'rounded-lg border p-3 text-center transition-all duration-200' , 'bg-gray-100 text-gray-400'=> $timeSlot['is_past'] && !$timeSlot['is_booked'],
                        'bg-red-100 text-red-800 border-red-300' => $timeSlot['is_booked'],
                        'bg-green-100 text-green-800 border-green-300 cursor-pointer hover:bg-green-200' => $timeSlot['is_available'] && $timeSlot['slot_type'] === 'free' && !$timeSlot['is_selected'],
                        'bg-purple-100 text-purple-800 border-purple-300 cursor-pointer hover:bg-purple-200' => $timeSlot['is_available'] && $timeSlot['slot_type'] === 'premium' && !$timeSlot['is_selected'],
                        'bg-green-200 text-green-900 border-green-400 shadow-inner' => $timeSlot['is_selected'] && $timeSlot['slot_type'] === 'free',
                        'bg-purple-200 text-purple-900 border-purple-400 shadow-inner' => $timeSlot['is_selected'] && $timeSlot['slot_type'] === 'premium',
                        ])
                        @if($timeSlot['is_available'])
                        wire:click="toggleTimeSlot('{{ $timeSlot['slot_key'] }}')"
                        @endif>
                        <div class="font-semibold">{{ $timeSlot['start_time'] }} - {{ $timeSlot['end_time'] }}</div>
                        @if($timeSlot['is_past'])
                        <div class="text-xs">Past</div>
                        @elseif($timeSlot['is_booked'])
                        <div class="text-xs">Booked</div>
                        @elseif($timeSlot['is_selected'])
                        <div class="text-xs">✓ Selected</div>
                        @elseif($timeSlot['is_available'])
                        <div class="text-xs">{{ $timeSlot['slot_type'] === 'free' ? '🆓 Free' : '⭐ Premium' }}</div>
                        @if($timeSlot['is_peak'])
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
                    <div class="text-sm text-gray-600">
                        Click on available time slots to select them for booking
                    </div>
                    <button
                        wire:click="closeTimeSelector"
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
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
        <div class="w-full max-w-2xl transform rounded-xl bg-white shadow-2xl">
            <!-- Header -->
            <div class="border-b border-gray-200 bg-gray-50 p-4 rounded-t-xl">
                <h3 class="text-lg font-bold text-gray-800">📅 Jump to Date</h3>

                <!-- Date Picker Mode Selector -->
                <div class="mt-3 flex gap-1 rounded-lg bg-white p-1 border">
                    <button
                        wire:click="setDatePickerMode('day')"
                        @class([ 'flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all duration-200' , 'bg-blue-500 text-white'=> $datePickerMode === 'day',
                        'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'day'
                        ])>
                        📅 Day
                    </button>
                    <button
                        wire:click="setDatePickerMode('week')"
                        @class([ 'flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all duration-200' , 'bg-blue-500 text-white'=> $datePickerMode === 'week',
                        'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'week'
                        ])>
                        📅 Week
                    </button>
                    <button
                        wire:click="setDatePickerMode('month')"
                        @class([ 'flex-1 rounded-md px-3 py-2 text-sm font-medium transition-all duration-200' , 'bg-blue-500 text-white'=> $datePickerMode === 'month',
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                        <select wire:model.live="selectedMonth" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            @foreach($availableMonths as $value => $name)
                            <option value="{{ $value }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                        <select wire:model.live="selectedYear" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            @foreach($availableYears as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Calendar Content -->
            <div class="p-4 max-h-96 overflow-y-auto">
                @if($datePickerMode === 'day')
                <!-- Day Picker -->
                <div class="grid grid-cols-7 gap-1 mb-2">
                    @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                    <div class="p-2 text-center text-xs font-medium text-gray-500">{{ $dayName }}</div>
                    @endforeach
                </div>

                <div class="grid grid-cols-7 gap-1">
                    @foreach($calendarDays as $day)
                    <button
                        wire:click="selectDate('{{ $day['date'] }}')"
                        @class([ 'aspect-square p-2 text-sm rounded-lg transition-all duration-200 hover:scale-105' , 'text-gray-900 hover:bg-blue-50 border border-transparent hover:border-blue-200'=> $day['is_current_month'] && !$day['is_past'] && !$day['is_today'] && $day['booking_type'] === 'none',
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
                        @class([ 'w-full p-3 rounded-lg border text-left transition-all duration-200 hover:scale-105' , 'bg-blue-100 border-blue-300 text-blue-800'=> $week['is_current_week'],
                        'bg-gray-100 border-gray-300 text-gray-500' => $week['is_past_week'],
                        'bg-green-100 border-green-300 text-green-800 hover:bg-green-200' => $week['can_book_free'] && !$week['is_current_week'] && !$week['is_past_week'],
                        'bg-purple-100 border-purple-300 text-purple-800 hover:bg-purple-200' => $week['can_book_premium'] && !$week['can_book_free'] && !$week['is_current_week'] && !$week['is_past_week'],
                        'bg-white border-gray-300 hover:bg-gray-50' => !$week['is_bookable'] && !$week['is_current_week'] && !$week['is_past_week']
                        ])>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium">Week {{ $week['week_number'] }}</div>
                                <div class="text-sm opacity-75">{{ $week['formatted_range'] }}</div>
                            </div>
                            <div class="text-right">
                                @if($week['is_current_week'])
                                <span class="text-xs bg-blue-200 px-2 py-1 rounded">Current</span>
                                @elseif($week['is_past_week'])
                                <span class="text-xs bg-gray-200 px-2 py-1 rounded">Past</span>
                                @elseif($week['can_book_free'])
                                <span class="text-xs bg-green-200 px-2 py-1 rounded">🆓 Free</span>
                                @elseif($week['can_book_premium'])
                                <span class="text-xs bg-purple-200 px-2 py-1 rounded">⭐ Premium</span>
                                @else
                                <span class="text-xs bg-gray-200 px-2 py-1 rounded">🔒 Locked</span>
                                @endif
                            </div>
                        </div>
                    </button>
                    @endforeach
                </div>

                @else
                <!-- Month Picker -->
                <div class="grid grid-cols-3 gap-3">
                    @foreach($calendarMonths as $month)
                    <button
                        wire:click="selectMonth('{{ $month['month_start'] }}')"
                        @class([ 'p-4 rounded-lg border text-center transition-all duration-200 hover:scale-105' , 'bg-blue-100 border-blue-300 text-blue-800'=> $month['is_current_month'],
                        'bg-gray-100 border-gray-300 text-gray-500' => $month['is_past_month'],
                        'bg-green-100 border-green-300 text-green-800 hover:bg-green-200' => $month['can_book_free'] && !$month['is_current_month'] && !$month['is_past_month'],
                        'bg-purple-100 border-purple-300 text-purple-800 hover:bg-purple-200' => $month['can_book_premium'] && !$month['can_book_free'] && !$month['is_current_month'] && !$month['is_past_month'],
                        'bg-white border-gray-300 hover:bg-gray-50' => !$month['is_bookable'] && !$month['is_current_month'] && !$month['is_past_month']
                        ])>
                        <div class="font-medium">{{ $month['month_name'] }}</div>
                        <div class="text-xs mt-1">
                            @if($month['is_current_month'])
                            Current
                            @elseif($month['is_past_month'])
                            Past
                            @elseif($month['booking_type'] === 'mixed')
                            🆓⭐ Mixed
                            @elseif($month['can_book_free'])
                            🆓 Free
                            @elseif($month['can_book_premium'])
                            ⭐ Premium
                            @else
                            🔒 Locked
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
                    <button
                        wire:click="closeModal"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Existing Modals (Confirm, Thank You, Login Reminder) -->
    @if($showConfirmModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="mx-4 w-full max-w-lg transform rounded-xl bg-white p-6 shadow-2xl">
            <h3 class="mb-6 text-xl font-bold">
                🎾 {{ $bookingType === 'mixed' ? 'Mixed' : ucfirst($bookingType) }} Booking Confirmation
            </h3>

            <div class="mb-6 space-y-4">
                @foreach($pendingBookingData as $booking)
                <div class="rounded-lg border bg-gray-50 p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-semibold">{{ $booking['date'] }}</div>
                            <div class="text-lg">{{ $booking['time'] }}</div>
                            @if($booking['is_light_required'])
                            <div class="mt-1 text-sm text-orange-600">
                                💡 Additional charges for court lights
                            </div>
                            @endif
                        </div>
                        <span @class([ 'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium' , 'bg-blue-100 text-blue-800'=> $booking['booking_type'] === 'free',
                            'bg-purple-100 text-purple-800' => $booking['booking_type'] !== 'free'
                            ])>
                            @if($booking['booking_type'] === 'free') 🆓 @else ⭐ @endif
                            {{ strtoupper($booking['booking_type']) }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-gray-600">
                <p>💳 Please process payment at reception before using the court</p>
                <p>⚠️ Please be responsible with bookings to avoid being blacklisted</p>
            </div>

            <div class="flex justify-end gap-3">
                <button
                    class="rounded-lg px-6 py-2 text-gray-600 transition-colors hover:text-gray-800"
                    wire:click="closeModal">
                    Cancel
                </button>
                <button
                    class="transform rounded-lg bg-gradient-to-r from-gray-700 to-gray-900 px-6 py-2 text-white transition-all duration-300 hover:scale-105 hover:from-gray-800 hover:to-black"
                    wire:click="processBooking">
                    🎾 CONFIRM BOOKING(S)
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($showThankYouModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="mx-4 w-full max-w-md transform rounded-xl bg-white p-8 text-center shadow-2xl">
            <div class="mb-4 text-6xl">🎾</div>
            <h3 class="mb-4 text-xl font-bold">Thank you for your booking!</h3>
            <div class="mb-6 rounded-lg bg-gray-100 py-4 text-3xl font-bold text-gray-800">
                #{{ $bookingReference }}
            </div>
            <button
                class="transform rounded-lg bg-gradient-to-r from-gray-600 to-gray-800 px-8 py-3 text-white transition-all duration-300 hover:scale-105 hover:from-gray-700 hover:to-gray-900"
                wire:click="closeModal">
                🏠 BACK TO BOOKING
            </button>
        </div>
    </div>
    @endif

    @if($showLoginReminder)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="mx-4 w-full max-w-md transform rounded-xl bg-white p-6 shadow-2xl">
            <h3 class="mb-4 text-lg font-bold">🔐 Login Required</h3>
            <p class="mb-6 text-gray-600">Please log in to your tenant account to proceed with booking.</p>
            <div class="flex justify-end gap-3">
                <button
                    class="px-4 py-2 text-gray-600 transition-colors hover:text-gray-800"
                    wire:click="closeModal">
                    Cancel
                </button>
                <button
                    class="rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                    wire:click="redirectToLogin">
                    🔑 Login
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
