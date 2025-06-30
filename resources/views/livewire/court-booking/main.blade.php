<?php

use App\Enum\BookingStatusEnum;
use App\Models\Booking;
use App\Settings\PremiumSettings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.frontend.layouts.app')] class extends Component
{
    // === CORE PROPERTIES ===
    public $courtNumber; // Which court we're booking (e.g., Court 2)

    public $selectedDate; // Currently selected date in daily view

    public $availableTimes = []; // Available time slots for daily view

    public $selectedSlots = []; // Array of selected time slots (format: "YYYY-MM-DD-HH:MM")

    public $quotaWarning = ''; // Warning message for quota violations

    public $quotaInfo; // User's quota information (used/remaining)

    // === BOOKING STATE ===
    public $bookingConfirmed = false; // Whether booking is confirmed

    public $confirmingBooking = false; // Whether we're in confirmation process

    public $bookingToConfirm; // Booking data being confirmed

    public $bookingType = 'free'; // Type of booking: 'free', 'premium', or 'mixed'

    public $bookingReference; // Generated booking reference number

    public $pendingBookingData = []; // Data for bookings being confirmed

    // === UI STATE ===
    public bool $compactView = false; // Toggle between compact and full view

    public $viewMode = 'monthly'; // Current view: 'weekly', 'monthly', or 'daily'

    // === DATE NAVIGATION ===
    public $currentDate; // Current date being viewed

    public $currentWeekStart; // Start of current week being viewed

    public $currentMonthStart; // Start of current month being viewed

    public $canGoBack = true; // Whether user can navigate backwards

    public $canGoForward = true; // Whether user can navigate forwards

    // === USER & PERMISSIONS ===
    public $isLoggedIn = false; // Whether user is logged in

    public $isPremiumBookingOpen = false; // Whether premium booking is currently open

    public $premiumBookingDate; // Date when premium booking opens (25th of month)

    // === DATA ARRAYS ===
    public $weekDays = []; // Array of days for weekly view

    public $monthDays = []; // Array of days for monthly view

    public $timeSlots = []; // Array of time slots (8am-10pm)

    public $bookedSlots = []; // Array of confirmed bookings

    public $preliminaryBookedSlots = []; // Array of pending bookings

    // === MODAL STATE ===
    public $showTimeSelector = false; // Show time selection modal (monthly view)

    public $showDatePicker = false; // Show date picker modal

    public $showConfirmModal = false; // Show booking confirmation modal

    public $showThankYouModal = false; // Show thank you modal after booking

    public $showLoginReminder = false; // Show login reminder modal

    // === DATE PICKER STATE ===
    public $selectedDateForTime; // Date selected for time picker modal

    public $availableTimesForDate = []; // Available times for selected date

    public $datePickerMode = 'day'; // Date picker mode: 'day', 'week', or 'month'

    public $selectedMonth; // Selected month in date picker

    public $selectedYear; // Selected year in date picker

    public $availableMonths = []; // Available months for selection

    public $availableYears = []; // Available years for selection

    public $calendarDays = []; // Calendar days for date picker

    public $calendarWeeks = []; // Calendar weeks for date picker

    public $calendarMonths = []; // Calendar months for date picker

    /**
     * Initialize the component when it's first loaded
     * Sets up default values and loads initial data
     */
    public function mount(PremiumSettings $premiumSettings)
    {
        // Set default court (hardcoded to Court 2 for now)
        $this->courtNumber = request()->route('id');

        // Initialize dates to today
        $this->selectedDate = now()->format('Y-m-d');
        $this->currentDate = now();
        $this->currentWeekStart = now()->startOfWeek();
        $this->currentMonthStart = now()->startOfMonth();

        // Set premium booking date using override if available, fallback to 25th
        $currentDate = now();
        $this->premiumBookingDate = \App\Models\PremiumDateOverride::getCurrentMonthPremiumDate();

        if ($currentDate->greaterThan($this->premiumBookingDate)) {
            $nextMonthPremiumDate = \App\Models\PremiumDateOverride::whereMonth('date', $currentDate->copy()->addMonth()->month)
                ->whereYear('date', $currentDate->copy()->addMonth()->year)
                ->first();

            $this->premiumBookingDate = $nextMonthPremiumDate ? \Carbon\Carbon::parse($nextMonthPremiumDate->date) : $currentDate->copy()->addMonth()->day(25);
        }

        // Check if premium booking is currently open
        $this->isPremiumBookingOpen = now()->format('Y-m-d') === $this->premiumBookingDate->format('Y-m-d');

        // Check if user is logged in
        $this->isLoggedIn = auth('tenant')->check();

        // Load initial data
        $this->generateAvailableTimesForDate();
        $this->quotaInfo = $this->getQuotaInfo();
        $this->loadBookedSlots();
        $this->generateTimeSlots();
        $this->generateWeekDays();
        $this->generateMonthDays();
        $this->initializeDatePicker();
    }

    /**
     * Get user's quota information (how many days they've used/have remaining)
     * Returns array with weekly usage data
     */
    public function getQuotaInfo()
    {
        // If not logged in, return empty quota
        if (! $this->isLoggedIn) {
            return ['weekly_remaining' => 0, 'weekly_used' => 0, 'weekly_total' => 3];
        }

        $tenant = auth('tenant')->user();

        // Count bookings for current week (Monday to Sunday)
        $weeklyBookings = $tenant->combined_booking_quota;

        return [
            'weekly_remaining' => $weeklyBookings['remaining'],
            'weekly_used' => $weeklyBookings['used'],
            'weekly_total' => $weeklyBookings['total'],
            'weekly_dates' => $weeklyBookings['dates'],
        ];
    }

    /**
     * Load booked and pending slots for the current view period
     * This populates the bookedSlots and preliminaryBookedSlots arrays
     */
    public function loadBookedSlots()
    {
        // Determine date range based on current view mode
        $startDate = $this->viewMode === 'weekly' ? $this->currentWeekStart : $this->currentMonthStart->copy()->startOfWeek();
        $endDate = $this->viewMode === 'weekly' ? $this->currentWeekStart->copy()->addWeek() : $this->currentMonthStart->copy()->endOfMonth()->endOfWeek();

        // Get all bookings for this court in the date range
        $bookings = Booking::where('court_id', $this->courtNumber)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with('tenant')
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->get();

        // Reset arrays
        $this->bookedSlots = [];
        $this->preliminaryBookedSlots = [];

        // Process each booking
        foreach ($bookings as $booking) {
            // Create slot key in format "YYYY-MM-DD-HH:MM"
            $slotKey = $booking->date->format('Y-m-d').'-'.$booking->start_time->format('H:i');

            // Create slot data with tenant info
            $slotData = [
                'key' => $slotKey,
                'tenant_name' => $booking->tenant->name ?? 'Unknown',
                'is_own_booking' => $this->isLoggedIn && $booking->tenant_id === auth('tenant')->id(),
            ];

            // Separate confirmed vs pending bookings
            if ($booking->status === BookingStatusEnum::CONFIRMED) {
                $this->bookedSlots[] = $slotData;
            } else {
                $this->preliminaryBookedSlots[] = $slotData;
            }
        }
    }

    /**
     * Called when selectedDate changes in daily view
     * Resets selections and regenerates available times
     */
    public function updatedSelectedDate()
    {
        $this->selectedSlots = [];
        $this->generateAvailableTimesForDate();
        $this->validateSelections();
    }

    /**
     * Generate available time slots for a specific date
     * Used by daily view and time selector modal
     *
     * @param  string|null  $date  - Date to generate times for (defaults to selectedDate)
     */
    public function generateAvailableTimesForDate($date = null)
    {
        // Use provided date or fall back to selectedDate
        $targetDate = $date ? Carbon::parse($date) : Carbon::parse($this->selectedDate);

        // Court operating hours: 8am to 10pm
        $startTime = Carbon::parse('08:00');
        $endTime = Carbon::parse('22:00');
        $interval = 60; // 60-minute slots

        // Reset arrays
        $this->availableTimes = [];
        $this->availableTimesForDate = [];

        // Get existing bookings for this date
        $bookedSlotsForDate = Booking::where('court_id', $this->courtNumber)
            ->where('date', $targetDate->format('Y-m-d'))
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->get()
            ->pluck('start_time')
            ->map(function ($time) {
                return $time->format('H:i');
            })
            ->toArray();

        // Generate time slots
        while ($startTime <= $endTime) {
            $time = $startTime->format('H:i');
            $slotKey = $targetDate->format('Y-m-d').'-'.$time;
            $slotType = $this->getSlotType($slotKey);
            $isBooked = in_array($time, $bookedSlotsForDate);
            $isSelected = in_array($slotKey, $this->selectedSlots);
            $isPast = $startTime->copy()->setDateFrom($targetDate)->isPast();

            // For daily view (simple array of available times)
            if (! $date) {
                if (! $isBooked) {
                    $this->availableTimes[] = $time;
                }
            }

            // For modal time selector (detailed slot information)
            $this->availableTimesForDate[] = [
                'start_time' => $time,
                'end_time' => $startTime->copy()->addHour()->format('H:i'),
                'slot_key' => $slotKey,
                'slot_type' => $slotType,
                'is_available' => ! $isBooked && ! $isPast && $this->canBookSlot($targetDate),
                'is_booked' => $isBooked,
                'is_selected' => $isSelected,
                'is_past' => $isPast,
                'is_peak' => $startTime->hour >= 18, // After 6pm = peak hours
            ];

            $startTime->addMinutes($interval);
        }
    }

    /**
     * Toggle selection of a time slot
     * Handles adding/removing slots and quota validation
     *
     * @param  string  $slotKey  - Slot key in format "YYYY-MM-DD-HH:MM"
     */
    public function toggleTimeSlot($slotKey)
    {
        // Check if user is logged in
        if ($this->isLoggedIn) {
            if (in_array($slotKey, $this->selectedSlots)) {
                // REMOVE SLOT: Simply remove from array
                $this->selectedSlots = array_diff($this->selectedSlots, [$slotKey]);
            } else {
                // if ($this->quotaInfo['weekly_remaining'] > 0) {
                // ADD SLOT: Check quotas first
                $parts = explode('-', $slotKey);
                if (count($parts) >= 3) {
                    $date = $parts[0].'-'.$parts[1].'-'.$parts[2];

                    // Count currently selected slots for this date
                    $dailySlots = array_filter($this->selectedSlots, function ($slot) use ($date) {
                        return str_starts_with($slot, $date);
                    });

                    // Count existing bookings for this date by this user
                    $existingBookingsForDate = 0;
                    if ($this->isLoggedIn) {
                        $existingBookingsForDate = Booking::where('court_id', $this->courtNumber)
                            ->where('date', $date)
                            ->where('status', '!=', BookingStatusEnum::CANCELLED)
                            ->where('tenant_id', auth('tenant')->id())
                            ->count();
                    }

                    $totalSlotsForDay = count($dailySlots) + $existingBookingsForDate;

                    // QUOTA CHECK: Max 2 hours (slots) per day
                    if ($totalSlotsForDay >= 2) {
                        $this->quotaWarning = 'Maximum 2 hours per day allowed (including existing bookings).';
                        // Don't add the slot, just show warning
                        $this->js("toast('{$this->quotaWarning}',{type:'warning'})");
                    } else {
                        // Add the slot and clear any warnings
                        $this->selectedSlots[] = $slotKey;
                        $this->quotaWarning = '';
                    }
                }
                // } else {
                //     $this->quotaWarning = 'You cannot book for more than 3 distinct days.';

                //     $this->js("toast('{$this->quotaWarning}',{type:'warning'})");

                //     return;
                // }
            }
        } else {
            $this->showLoginReminder = true;

            return;
        }

        // Re-validate all selections
        $this->validateSelections();

        // Refresh modal data if time selector is open
        if ($this->showTimeSelector) {
            $this->generateAvailableTimesForDate($this->selectedDateForTime);
        }
    }

    /**
     * Validate current slot selections against quotas
     * Sets quotaWarning if any limits are exceeded
     */
    public function validateSelections()
    {
        // If not logged in, no validation needed
        if (! $this->isLoggedIn) {
            return;
        }

        $tenantId = auth('tenant')->id();

        // Get unique dates from selected slots
        $selectedDates = collect($this->selectedSlots)
            ->map(function ($slot) {
                $parts = explode('-', $slot);

                return $parts[0].'-'.$parts[1].'-'.$parts[2];
            })
            ->unique();

        // Get existing bookings for the tenant
        $existingBookings = Booking::getBookedDaysForTenant($tenantId, Carbon::today()->format('Y-m-d'));

        // Count days with existing bookings
        $bookedDaysCount = $existingBookings->count();

        // Check if we're exceeding the 3-day limit
        if ($selectedDates->count() + $bookedDaysCount > 3) {
            // Check if all selected slots are for days that already have bookings
            $allSlotsForExistingDays = true;

            foreach ($selectedDates as $date) {
                // If this date doesn't have existing bookings, we're adding a new day
                if (! $existingBookings->has($date)) {
                    $allSlotsForExistingDays = false;
                    break;
                }

                // Check if the day already has 2 bookings (max quota per day)
                $bookingsOnThisDay = $existingBookings->get($date)->count();
                if ($bookingsOnThisDay >= 2) {
                    $this->quotaWarning = 'Maximum 2 hours per day allowed.';
                    $this->js("toast('{$this->quotaWarning}',{type:'warning'})");

                    return false;
                }

                // Count selected slots for this date
                $selectedSlotsForThisDate = collect($this->selectedSlots)
                    ->filter(function ($slot) use ($date) {
                        return str_starts_with($slot, $date);
                    })
                    ->count();

                // Check if total would exceed daily limit
                if ($bookingsOnThisDay + $selectedSlotsForThisDate > 2) {
                    $this->quotaWarning = 'Maximum 2 hours per day allowed.';
                    $this->js("toast('{$this->quotaWarning}',{type:'warning'})");

                    return;
                }
            }

            // If we're only adding slots to days that already have bookings
            // AND we're not exceeding the 2-hour per day limit, allow it
            if ($allSlotsForExistingDays) {
                $this->quotaWarning = '';

                return;
            }

            // Otherwise, we're trying to add a new day beyond the 3-day limit
            $this->quotaWarning = 'You cannot book for more than 3 distinct days.';
            $this->js("toast('{$this->quotaWarning}',{type:'warning'})");

            return;
        }

        // For each selected date, check if we're exceeding the 2-hour per day limit
        foreach ($selectedDates as $date) {
            // Count existing bookings for this date
            $existingBookingsForDate = $existingBookings->has($date) ? $existingBookings->get($date)->count() : 0;

            // Count selected slots for this date
            $selectedSlotsForThisDate = collect($this->selectedSlots)
                ->filter(function ($slot) use ($date) {
                    return str_starts_with($slot, $date);
                })
                ->count();

            // Check if total would exceed daily limit
            if ($existingBookingsForDate + $selectedSlotsForThisDate > 2) {
                $this->quotaWarning = 'Maximum 2 hours per day allowed.';
                $this->js("toast('{$this->quotaWarning}',{type:'warning'})");

                return;
            }
        }

        $this->quotaWarning = ''; // Clear any previous warnings
    }

    /**
     * Confirm and create the bookings
     * This is the main booking creation function
     */
    public function confirmBooking()
    {
        // Check if user is logged in
        if (! $this->isLoggedIn) {
            $this->showLoginReminder = true;

            return;
        }

        // Validate selections one more time
        $this->validateSelections();

        // Don't proceed if there are quota violations
        if (! empty($this->quotaWarning)) {
            return;
        }

        // Don't proceed if no slots selected
        if (empty($this->selectedSlots)) {
            return;
        }

        // Prepare booking data for confirmation modal
        $this->pendingBookingData = [];
        foreach ($this->selectedSlots as $slot) {
            $parts = explode('-', $slot);
            if (count($parts) >= 4) {
                $date = $parts[0].'-'.$parts[1].'-'.$parts[2];
                $startTime = count($parts) == 4 ? $parts[3] : $parts[3].':'.$parts[4];
                $bookingDate = Carbon::parse($date);
                $slotType = $this->getDateBookingType($bookingDate);

                $this->pendingBookingData[] = [
                    'court_id' => $this->courtNumber,
                    'date' => $bookingDate,
                    'start_time' => $startTime,
                    'end_time' => (new DateTime($startTime))->modify('+1 hour')->format('H:i'),
                    'status' => BookingStatusEnum::PENDING,
                    'booking_type' => $slotType,
                    'booking_week_start' => $bookingDate->copy()->startOfWeek()->format('Y-m-d'),
                    'price' => $slotType === 'premium' ? 150000 : 0,
                    'light_surcharge' => Carbon::createFromFormat('H:i', $startTime)->hour >= 18 ? 50000 : 0,
                    'is_light_required' => Carbon::createFromFormat('H:i', $startTime)->hour >= 18,
                ];
            }
        }
        usort($this->pendingBookingData, function ($a, $b) {
            return $a['date'] <=> $b['date'] ?: $a['start_time'] <=> $b['start_time'];
        });

        // Show confirmation modal
        $this->showConfirmModal = true;
    }

    /**
     * Actually process the booking after confirmation
     * Creates database records
     */
    public function processBooking()
    {
        $tenant = auth('tenant')->user();

        // Create each booking in the database
        foreach ($this->pendingBookingData as $slot) {
            try {
                // CREATE BOOKING RECORD
                $booking = Booking::create([...$slot, 'tenant_id' => $tenant->id, 'booking_reference' => $this->bookingReference]);
                $this->bookingReference = $booking->generateReference();

            } catch (\Exception $e) {
                // Log detailed error information for debugging
                Log::error('Booking creation failed: '.$e->getMessage(), [
                    'slot' => $slot,
                    'tenant_id' => $tenant->id,
                    'court_id' => $this->courtNumber,
                ]);

                $this->quotaWarning = 'Failed to create booking. Please try again.';

                return;
            }
        }

        // Reset state after successful booking
        $this->selectedSlots = [];
        $this->generateAvailableTimesForDate();
        $this->quotaInfo = $this->getQuotaInfo();
        $this->loadBookedSlots();
        $this->quotaWarning = '';

        // Flash success message
        session()->flash('message', 'Booking request sent successfully!');

        // Show thank you modal
        $this->showConfirmModal = false;
        $this->showThankYouModal = true;
    }

    /**
     * Toggle between compact and full view modes
     */
    public function toggleCompactView()
    {
        $this->compactView = ! $this->compactView;
    }

    /**
     * Open the date picker modal
     */
    public function openDatePicker()
    {
        $this->showDatePicker = true;
        $this->initializeDatePicker();
    }

    /**
     * Close the date picker modal
     */
    public function closeDatePicker()
    {
        $this->showDatePicker = false;
    }

    /**
     * Generate the standard time slots (8am-10pm, 1-hour intervals)
     * Used by all views to show available booking times
     */
    public function generateTimeSlots()
    {
        $this->timeSlots = [];
        $start = Carbon::parse('08:00');
        $end = Carbon::parse('22:00');

        while ($start < $end) {
            $this->timeSlots[] = [
                'start' => $start->format('H:i'),
                'end' => $start->copy()->addHour()->format('H:i'),
                'is_peak' => $start->hour >= 18, // After 6pm = peak hours (lights required)
            ];
            $start->addHour();
        }
    }

    /**
     * Generate days for weekly view
     * Creates array of 7 days starting from currentWeekStart
     */
    public function generateWeekDays()
    {
        $this->weekDays = [];
        $start = $this->currentWeekStart->copy();

        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $this->weekDays[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('D'), // Mon, Tue, etc.
                'day_number' => $date->format('j'), // 1, 2, 3, etc.
                'month_name' => $date->format('M'), // Jan, Feb, etc.
                'is_today' => $date->isToday(),
                'is_past' => $date->isPast(),
                'is_bookable' => $this->canBookSlot($date),
                'can_book_free' => $this->canBookFree($date),
                'can_book_premium' => $this->canBookPremium($date),
                'formatted_date' => $date->format('M j, Y'),
            ];
        }
    }

    /**
     * Generate days for monthly view
     * Creates calendar grid including days from previous/next month
     */
    public function generateMonthDays()
    {
        $this->monthDays = [];

        // Start from Monday of first week, end on Sunday of last week
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
                'available_count' => $bookingCounts['available'],
            ];
            $start->addDay();
        }
    }

    /**
     * Get booking counts for a specific date
     * Returns array with counts of different booking types
     *
     * @param  Carbon  $date  - Date to count bookings for
     * @return array - Counts of booked, pending, selected, available slots
     */
    public function getDateBookingCounts($date)
    {
        $dateStr = $date->format('Y-m-d');

        // Count confirmed bookings for this date
        $bookedCount = collect($this->bookedSlots)
            ->filter(function ($slot) use ($dateStr) {
                return str_starts_with($slot['key'], $dateStr);
            })
            ->count();

        // Count pending bookings for this date
        $pendingCount = collect($this->preliminaryBookedSlots)
            ->filter(function ($slot) use ($dateStr) {
                return str_starts_with($slot['key'], $dateStr);
            })
            ->count();

        // Count currently selected slots for this date
        $selectedCount = collect($this->selectedSlots)
            ->filter(function ($slot) use ($dateStr) {
                return str_starts_with($slot, $dateStr);
            })
            ->count();

        // Calculate available slots (total 14 slots: 8am-10pm)
        $totalSlots = 14;
        $availableCount = $totalSlots - $bookedCount - $pendingCount;

        return [
            'booked' => $bookedCount,
            'pending' => $pendingCount,
            'selected' => $selectedCount,
            'available' => max(0, $availableCount),
        ];
    }

    // === BOOKING RULES FUNCTIONS ===
    // These functions determine when users can book slots

    /**
     * Check if a slot can be booked on this date
     *
     * @param  Carbon  $date
     * @return bool
     */
    public function canBookSlot($date)
    {
        return $this->canBookFree($date) || $this->canBookPremium($date);
    }

    /**
     * Check if free booking is available for this date
     * Rule: Free booking only for next week (Monday to Sunday)
     *
     * @param  Carbon  $date
     * @return bool
     */
    public function canBookFree($date)
    {
        $nextWeekStart = now()->addWeek()->startOfWeek();
        $nextWeekEnd = now()->addWeek()->endOfWeek();

        return $date->between($nextWeekStart, $nextWeekEnd);
    }

    /**
     * Check if premium booking is available for this date
     * Rule: Premium booking for dates beyond next week, and only if premium booking is open
     */
    public function canBookPremium(Carbon $date): bool
    {
        $nextWeekEnd = now()->addWeek()->endOfWeek();
        $premiumEnd = now()->addMonth()->endOfMonth();

        return $date->gt($nextWeekEnd) && $date->lte($premiumEnd) && $this->isPremiumBookingOpen;
    }

    /**
     * Get the booking type for a specific date
     *
     * @param  Carbon  $date
     * @return string - 'free', 'premium', or 'none'
     */
    public function getDateBookingType($date)
    {
        if ($this->canBookFree($date)) {
            return 'free';
        }
        if ($this->canBookPremium($date)) {
            return 'premium';
        }

        return 'none';
    }

    /**
     * Get detailed booking information for a date
     *
     * @param  Carbon  $date
     * @return array
     */
    public function getDateBookingInfo($date)
    {
        return [
            'can_book_free' => $this->canBookFree($date),
            'can_book_premium' => $this->canBookPremium($date),
            'is_bookable' => $this->canBookSlot($date),
        ];
    }

    /**
     * Get the booking type for a specific slot key
     *
     * @param  string  $slotKey  - Format: "YYYY-MM-DD-HH:MM"
     * @return string - 'free', 'premium', or 'none'
     */
    public function getSlotType($slotKey)
    {
        $parts = explode('-', $slotKey);
        if (count($parts) >= 3) {
            $date = Carbon::createFromFormat('Y-m-d', $parts[0].'-'.$parts[1].'-'.$parts[2]);

            return $this->getDateBookingType($date);
        }

        return 'none';
    }

    // === VIEW SWITCHING FUNCTIONS ===

    /**
     * Switch between different view modes
     *
     * @param  string  $mode  - 'weekly', 'monthly', or 'daily'
     */
    public function switchView($mode)
    {
        $this->viewMode = $mode;
        // Reload booking data for new view
        $this->loadBookedSlots();
        // Generate appropriate data for the new view
        if ($mode === 'weekly') {
            $this->generateWeekDays();
        } elseif ($mode === 'monthly') {
            $this->generateMonthDays();
        } elseif ($mode === 'daily') {
            $this->selectedDate = $this->currentDate->format('Y-m-d');
            $this->generateAvailableTimesForDate();
        }

    }

    // === NAVIGATION FUNCTIONS ===

    /**
     * Navigate to previous period (week/month/day)
     */
    public function previousPeriod()
    {
        if ($this->viewMode === 'weekly') {
            $this->currentWeekStart = $this->currentWeekStart->subWeek();
            $this->loadBookedSlots();
            $this->generateWeekDays();
        } elseif ($this->viewMode === 'monthly') {
            $this->currentMonthStart = $this->currentMonthStart->subMonth();
            $this->loadBookedSlots();
            $this->generateMonthDays();
        } elseif ($this->viewMode === 'daily') {
            $this->currentDate = $this->currentDate->subDay();
            $this->selectedDate = $this->currentDate->format('Y-m-d');
            $this->loadBookedSlots();
            $this->generateAvailableTimesForDate();
        }
        $this->loadBookedSlots();
    }

    /**
     * Navigate to next period (week/month/day)
     */
    public function nextPeriod()
    {
        if ($this->viewMode === 'weekly') {
            $this->currentWeekStart = $this->currentWeekStart->addWeek();
            $this->loadBookedSlots();
            $this->generateWeekDays();
        } elseif ($this->viewMode === 'monthly') {
            $this->currentMonthStart = $this->currentMonthStart->addMonth();
            $this->loadBookedSlots();
            $this->generateMonthDays();
        } elseif ($this->viewMode === 'daily') {
            $this->currentDate = $this->currentDate->addDay();
            $this->selectedDate = $this->currentDate->format('Y-m-d');
            $this->loadBookedSlots();
            $this->generateAvailableTimesForDate();
        }
    }

    /**
     * Navigate to today
     */
    public function goToToday()
    {
        $this->currentDate = now();
        $this->currentWeekStart = now()->startOfWeek();
        $this->currentMonthStart = now()->startOfMonth();
        $this->selectedDate = now()->format('Y-m-d');

        // Regenerate data for current view
        if ($this->viewMode === 'weekly') {
            $this->loadBookedSlots();
            $this->generateWeekDays();
        } elseif ($this->viewMode === 'monthly') {
            $this->loadBookedSlots();
            $this->generateMonthDays();
        } else {
            $this->loadBookedSlots();
            $this->generateAvailableTimesForDate();
        }
        $this->loadBookedSlots();
    }

    // === MODAL FUNCTIONS ===

    /**
     * Close all modals
     */
    public function closeModal()
    {
        $this->showConfirmModal = false;
        $this->showThankYouModal = false;
        $this->showLoginReminder = false;
        $this->showTimeSelector = false;
        $this->showDatePicker = false;
    }

    /**
     * Show time selector modal for a specific date (used in monthly view)
     *
     * @param  string  $date  - Date to show times for
     */
    public function showTimesForDate($date)
    {
        if ($this->isLoggedIn) {
            $this->selectedDateForTime = $date;
            $this->showTimeSelector = true;
            $this->generateAvailableTimesForDate($date);
        } else {
            $this->showLoginReminder = true;
        }
    }

    /**
     * Close time selector modal
     */
    public function closeTimeSelector()
    {
        $this->showTimeSelector = false;
    }

    // === DATE PICKER FUNCTIONS ===

    /**
     * Initialize date picker with current date and available options
     */
    public function initializeDatePicker()
    {
        $this->selectedMonth = $this->currentDate->month;
        $this->selectedYear = $this->currentDate->year;

        // Set up available months
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
            12 => 'December',
        ];

        // Set up available years (current year + 2 years ahead)
        $this->availableYears = range(now()->year, now()->year + 2);

        // Generate calendar data
        $this->generateCalendarDays();
        $this->generateCalendarWeeks();
        $this->generateCalendarMonths();
    }

    /**
     * Generate calendar days for date picker
     */
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
                'formatted_date' => $current->format('M j, Y'),
            ];
            $current->addDay();
        }
    }

    /**
     * Generate calendar weeks for date picker
     */
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
                'formatted_range' => $current->format('M j').' - '.$weekEnd->format('M j'),
                'is_current_week' => now()->between($current, $weekEnd),
                'is_past_week' => $weekEnd->isPast(),
                'can_book_free' => $this->canBookFree($current),
                'can_book_premium' => $this->canBookPremium($current),
                'is_bookable' => $this->canBookSlot($current),
            ];
            $current->addWeek();
            $weekNumber++;
        }
    }

    /**
     * Generate calendar months for date picker
     */
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
                'is_bookable' => $this->canBookSlot($monthStart),
            ];
        }
    }

    /**
     * Set date picker mode (day/week/month)
     *
     * @param  string  $mode
     */
    public function setDatePickerMode($mode)
    {
        $this->datePickerMode = $mode;
        if ($mode === 'day') {
            $this->generateCalendarDays();
        } elseif ($mode === 'week') {
            $this->generateCalendarWeeks();
        } elseif ($mode === 'month') {
            $this->generateCalendarMonths();
        }
    }

    /**
     * Select a specific date from date picker
     *
     * @param  string  $date
     */
    public function selectDate($date)
    {
        $selectedDate = Carbon::parse($date);
        $this->currentDate = $selectedDate;
        $this->selectedDate = $date;

        // Update view based on current mode
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

    /**
     * Select a specific week from date picker
     *
     * @param  string  $weekStart
     */
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

    /**
     * Select a specific month from date picker
     *
     * @param  string  $monthStart
     */
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

    /**
     * Redirect to login page
     */
    public function redirectToLogin()
    {
        return redirect()->route('login');
    }

    /**
     * Called when month is changed in date picker
     */
    public function updatedSelectedMonth()
    {
        $this->generateCalendarDays();
        $this->generateCalendarWeeks();
        $this->generateCalendarMonths();
    }

    /**
     * Called when year is changed in date picker
     */
    public function updatedSelectedYear()
    {
        $this->generateCalendarDays();
        $this->generateCalendarWeeks();
        $this->generateCalendarMonths();
    }
}?>

<div>
    <!-- Header -->
    <div class="relative overflow-hidden bg-gradient-to-r from-gray-600 to-gray-800 py-8 text-center text-white">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="relative z-10">
            <h1 class="text-3xl font-bold tracking-wide">🎾 TENNIS COURT {{ $this->courtNumber }} BOOKING</h1>
            <p class="mt-2 text-gray-200">Reserve your perfect playing time</p>

            <!-- Booking Status Indicators -->
            <div class="mt-4 flex justify-center gap-4 text-sm">
                <div class="flex items-center gap-2 rounded-full bg-green-600 px-3 py-1">
                    <div class="h-2 w-2 rounded-full bg-green-300"></div>
                    <span>🆓 Free Booking: Next Week</span>
                </div>
                @if ($isPremiumBookingOpen)
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
        <div class="mb-6 flex flex-col items-center justify-center gap-4 sm:flex-row">
            <div class="inline-flex rounded-lg border border-gray-300 bg-white p-1 shadow-sm">

                <button wire:click="switchView('monthly')" @class([
                    'rounded-md px-4 py-2 text-sm font-medium transition-all duration-200',
                    'bg-blue-500 text-white shadow-sm' => $viewMode === 'monthly',
                    'text-gray-700 hover:bg-gray-100' => $viewMode !== 'monthly',
                ])>
                    📆 Monthly
                </button>

                <button wire:click="switchView('weekly')" @class([
                    'rounded-md px-4 py-2 text-sm font-medium transition-all duration-200',
                    'bg-blue-500 text-white shadow-sm' => $viewMode === 'weekly',
                    'text-gray-700 hover:bg-gray-100' => $viewMode !== 'weekly',
                ])>
                    📅 Weekly
                </button>

                <button wire:click="switchView('daily')" @class([
                    'rounded-md px-4 py-2 text-sm font-medium transition-all duration-200',
                    'bg-blue-500 text-white shadow-sm' => $viewMode === 'daily',
                    'text-gray-700 hover:bg-gray-100' => $viewMode !== 'daily',
                ])>
                    🕐 Daily
                </button>

            </div>

            <!-- Compact View Toggle -->
            <button wire:click="toggleCompactView" @class([
                'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-200',
                'bg-gray-600 text-white' => $compactView,
                'bg-gray-100 text-gray-700 hover:bg-gray-200' => !$compactView,
            ])>
                @if ($compactView)
                    📱 Compact
                @else
                    🖥️ Full
                @endif
            </button>
        </div>

        <!-- Booking Rules Info -->
        @if (!$compactView)
            <div class="mb-6 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-purple-50 p-4">
                <h3 class="mb-2 font-bold text-gray-800">📋 Booking Rules</h3>
                <div class="grid gap-2 text-sm md:grid-cols-2">
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-green-500"></div>
                        <span><strong>Free Booking:</strong> Next week only
                            ({{ Carbon::today()->addWeek()->startOfWeek()->format('M j') }} -
                            {{ Carbon::today()->addWeek()->endOfWeek()->format('M j') }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-3 w-3 rounded-full bg-purple-500"></div>
                        <span><strong>Premium Booking:</strong> Beyond next week @if ($isPremiumBookingOpen)
                                (Open Now!)
                            @else
                            (Opens {{ $premiumBookingDate->format('M j') }})
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        @endif

        <!-- Login Prompt -->
        @if (!$isLoggedIn)
            <div @class([
                'mb-6 rounded-r-lg border-l-4 border-blue-400 bg-blue-50',
                'p-3' => $compactView,
                'p-6' => !$compactView,
            ])>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p @class([
                            'text-blue-700',
                            'text-xs' => $compactView,
                            'text-sm' => !$compactView,
                        ])>
                            <strong>Login to see your booking quota</strong> and make reservations.
                            <a class="underline transition-colors hover:text-blue-900" href="{{ route('login') }}">Sign
                                in here</a>
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Quota Display -->
        @if ($isLoggedIn && !empty($quotaInfo))
            <div @class([
                'mb-6 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100 shadow-sm',
                'p-3' => $compactView,
                'p-6' => !$compactView,
            ])>
                <div class="flex items-center justify-between">
                    <div>
                        <h3 @class([
                            'font-bold text-blue-800',
                            'text-sm' => $compactView,
                            'text-lg' => !$compactView,
                        ])>Weekly Quota</h3>
                        @if (!$compactView)
                            <p class="text-sm text-blue-600">Maximum 3 distinct days, 2 hours per day</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <div @class([
                            'font-bold text-blue-600',
                            'text-xl' => $compactView,
                            'text-3xl' => !$compactView,
                        ])>
                            {{ $quotaInfo['weekly_used'] ?? 0 }}/{{ $quotaInfo['weekly_total'] ?? 3 }}
                        </div>
                        <div @class([
                            'text-blue-600',
                            'text-xs' => $compactView,
                            'text-sm' => !$compactView,
                        ])>Days used</div>
                    </div>
                </div>
                @if (($quotaInfo['weekly_remaining'] ?? 0) > 0)
                    <div @class([
                        'mt-2 text-green-600',
                        'text-xs' => $compactView,
                        'text-sm' => !$compactView,
                    ])>
                        ✅ You can book {{ $quotaInfo['weekly_remaining'] }} more days this week
                    </div>
                @else
                    <div @class([
                        'mt-2 text-red-600',
                        'text-xs' => $compactView,
                        'text-sm' => !$compactView,
                    ])>
                        ⚠️ You have reached your booking limit
                    </div>
                @endif
            </div>
        @endif

        <!-- Quota Warning -->
        @if ($quotaWarning)
            <div @class([
                'mb-6 rounded-r-lg border-l-4 border-orange-400 bg-orange-50',
                'p-3' => $compactView,
                'p-4' => !$compactView,
            ])>
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p @class([
                            'text-orange-700',
                            'text-xs' => $compactView,
                            'text-sm' => !$compactView,
                        ])>⚠️ {{ $quotaWarning }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Navigation Controls -->
        <div @class([
            'mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 shadow-sm',
            'p-2' => $compactView,
            'p-4' => !$compactView,
        ])>
            <button wire:click="previousPeriod" @class([
                'flex items-center gap-2 rounded-lg transition-all duration-300',
                'px-2 py-1 text-sm' => $compactView,
                'px-4 py-2' => !$compactView,
                'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 cursor-pointer shadow-sm',
            ])>
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                @if (!$compactView)
                    Previous
                @endif
            </button>

            <div class="flex items-center gap-2">
                <div class="text-center">
                    @if ($viewMode === 'weekly')
                        <h3 @class([
                            'font-semibold',
                            'text-sm' => $compactView,
                            'text-lg' => !$compactView,
                        ])>
                            {{ $currentWeekStart->format('M j') }} -
                            {{ $currentWeekStart->copy()->addDays(6)->format('M j, Y') }}
                        </h3>
                    @elseif($viewMode === 'monthly')
                        <h3 @class([
                            'font-semibold',
                            'text-sm' => $compactView,
                            'text-lg' => !$compactView,
                        ])>{{ $currentMonthStart->format('F Y') }}</h3>
                    @else
                        <h3 @class([
                            'font-semibold',
                            'text-sm' => $compactView,
                            'text-lg' => !$compactView,
                        ])>{{ $currentDate->format('l, F j, Y') }}</h3>
                    @endif
                </div>

                <!-- Date Picker Button -->
                <button wire:click="openDatePicker" @class([
                    'rounded-lg bg-purple-100 text-purple-700 transition-all duration-300 hover:bg-purple-200',
                    'px-2 py-1 text-xs' => $compactView,
                    'px-3 py-1 ml-2' => !$compactView,
                ])>
                    📅 @if (!$compactView)
                        Jump to Date
                    @endif
                </button>
            </div>

            <div class="flex items-center gap-2">
                <button wire:click="goToToday" @class([
                    'rounded-lg bg-blue-100 text-blue-700 transition-all duration-300 hover:bg-blue-200',
                    'px-2 py-1 text-xs' => $compactView,
                    'px-4 py-2' => !$compactView,
                ])>
                    📅 @if (!$compactView)
                        Today
                    @endif
                </button>

                <button wire:click="nextPeriod" @class([
                    'flex items-center gap-2 rounded-lg transition-all duration-300',
                    'px-2 py-1 text-sm' => $compactView,
                    'px-4 py-2' => !$compactView,
                    'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 cursor-pointer shadow-sm',
                ])>
                    @if (!$compactView)
                        Next
                    @endif
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Weekly View -->
        @if ($viewMode === 'weekly')
            @include('livewire.court-booking.partials.weekly-view')
        @endif

        <!-- Monthly View -->
        @if ($viewMode === 'monthly')
            @include('livewire.court-booking.partials.monthly-view')
        @endif

        <!-- Daily View -->
        @if ($viewMode === 'daily')
            @include('livewire.court-booking.partials.daily-view')
        @endif

        <!-- Selection Summary -->
        @if (count($selectedSlots) > 0)
            <div @class([
                'mb-8 rounded-xl border border-green-200 bg-gradient-to-r from-green-50 to-blue-50 shadow-sm',
                'p-3' => $compactView,
                'p-6' => !$compactView,
            ])>
                <h4 @class([
                    'mb-4 flex items-center gap-2 font-bold text-gray-800',
                    'text-sm mb-2' => $compactView,
                    '' => !$compactView,
                ])>
                    🎯 Selected Time Slots ({{ count($selectedSlots) }})
                    @if ($bookingType === 'mixed')
                        <span @class([
                            'rounded-full bg-gradient-to-r from-blue-500 to-purple-500 text-white',
                            'px-2 py-1 text-xs' => !$compactView,
                            'px-1 text-xs' => $compactView,
                        ])>
                            @if ($compactView)
                                Mixed
                            @else
                                Mixed Booking
                            @endif
                        </span>
                    @endif
                </h4>
                <div @class([
                    'flex flex-wrap',
                    'gap-1' => $compactView,
                    'gap-3' => !$compactView,
                ])>
                    @foreach ($selectedSlots as $slot)
                        @php
                            $parts = explode('-', $slot);
                            if (count($parts) >= 4) {
                                $date = Carbon::createFromFormat(
                                    'Y-m-d',
                                    $parts[0] . '-' . $parts[1] . '-' . $parts[2],
                                );
                                $time = count($parts) == 4 ? $parts[3] : $parts[3] . ':' . $parts[4];
                                $slotType = $this->getSlotType($slot);
                            }
                        @endphp
                        @if (isset($date) && isset($time))
                            <span @class([
                                'inline-flex items-center rounded-full font-medium transition-all duration-300 hover:scale-105',
                                'px-2 py-1 text-xs' => $compactView,
                                'px-4 py-2 text-sm' => !$compactView,
                                'bg-gradient-to-r from-green-100 to-green-200 text-green-800 border border-green-300' =>
                                    $slotType === 'free',
                                'bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 border border-purple-300' =>
                                    $slotType !== 'free',
                            ])>
                                @if ($slotType === 'free')
                                    🆓
                                @else
                                    ⭐
                                @endif
                                {{ $date->format('M j') }} @if (!$compactView)
                                    at
                                @endif {{ $time }}
                                <button @class([
                                    'ml-2 transition-transform duration-200 hover:scale-110',
                                    'ml-1' => $compactView,
                                    'text-green-600 hover:text-green-800' => $slotType === 'free',
                                    'text-purple-600 hover:text-purple-800' => $slotType !== 'free',
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
        @if ($compactView)
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
        @if (!$compactView)
            <div
                class="mb-8 flex flex-wrap items-center gap-6 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 p-6 text-sm">
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
            <button wire:click="confirmBooking" @disabled(count($selectedSlots) === 0 || $quotaWarning) @class([
                'transform rounded-xl font-bold shadow-lg transition-all duration-500 hover:scale-105',
                'px-4 py-2 text-xs' => $compactView,
                'px-8 py-4 text-sm' => !$compactView,
                'bg-gray-300 text-gray-500 cursor-not-allowed' =>
                    count($selectedSlots) === 0,
                'bg-orange-400 text-white cursor-not-allowed' => $quotaWarning,
                'bg-gradient-to-r from-gray-700 via-gray-800 to-gray-900 text-white hover:from-gray-800 hover:via-gray-900 hover:to-black cursor-pointer hover:shadow-xl' =>
                    !$quotaWarning && count($selectedSlots) > 0,
            ])>
                @if ($quotaWarning)
                    ⚠️ @if ($compactView)
                        QUOTA
                    @else
                        QUOTA EXCEEDED
                    @endif
                @else
                    🎾 @if ($compactView)
                        BOOK
                    @else
                        CONFIRM
                    @endif
                    @if (!$compactView)
                        @if ($bookingType === 'mixed')
                            MIXED
                        @else
                            {{ strtoupper($bookingType) }}
                        @endif
                        BOOKING(S)
                    @endif
                    @if (count($selectedSlots) > 0)
                        ({{ count($selectedSlots) }})
                    @endif
                @endif
            </button>
        </div>
    </div>

    <!-- Time Selector Modal for Monthly View -->
    @if ($showTimeSelector)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
            <div @class([
                'w-full transform rounded-xl bg-white shadow-2xl',
                'max-w-lg' => $compactView,
                'max-w-2xl' => !$compactView,
            ])>
                <!-- Header -->
                <div class="rounded-t-xl border-b border-gray-200 bg-gray-50 p-4">
                    <div class="flex items-center justify-between">
                        <h3 @class([
                            'font-bold text-gray-800',
                            'text-sm' => $compactView,
                            'text-lg' => !$compactView,
                        ])>
                            🕐 Select Time for
                            {{ Carbon::parse($selectedDateForTime)->format($compactView ? 'M j, Y' : 'l, F j, Y') }}
                        </h3>
                        <button class="text-gray-400 transition-colors hover:text-gray-600"
                            wire:click="closeTimeSelector">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    @php $dayInfo = $this->getDateBookingInfo(Carbon::parse($selectedDateForTime)); @endphp
                    <div class="mt-2 flex items-center gap-2">
                        @if ($dayInfo['can_book_free'])
                            <span @class([
                                'rounded-full bg-green-200 text-green-700',
                                'px-2 py-1 text-xs' => !$compactView,
                                'px-1 text-xs' => $compactView,
                            ])>🆓 @if (!$compactView)
                                    Free Booking Available
                                @endif
                            </span>
                        @endif
                        @if ($dayInfo['can_book_premium'])
                            <span @class([
                                'rounded-full bg-purple-200 text-purple-700',
                                'px-2 py-1 text-xs' => !$compactView,
                                'px-1 text-xs' => $compactView,
                            ])>⭐ @if (!$compactView)
                                    Premium Booking Available
                                @endif
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Time Slots Grid -->
                <div @class([
                    'max-h-96 overflow-y-auto',
                    'p-2' => $compactView,
                    'p-4' => !$compactView,
                ])>
                    <div @class([
                        'grid gap-2',
                        'grid-cols-3' => $compactView,
                        'sm:grid-cols-2 lg:grid-cols-3' => !$compactView,
                    ])>
                        @foreach ($availableTimesForDate as $timeSlot)
                            <div @class([
                                'rounded-lg border text-center transition-all duration-200',
                                'p-2' => $compactView,
                                'p-3' => !$compactView,
                                'bg-gray-100 text-gray-400' =>
                                    $timeSlot['is_past'] && !$timeSlot['is_booked'],
                                'bg-red-100 text-red-800 border-red-300' => $timeSlot['is_booked'],
                                'bg-green-100 text-green-800 border-green-300 cursor-pointer hover:bg-green-200' =>
                                    $timeSlot['is_available'] &&
                                    $timeSlot['slot_type'] === 'free' &&
                                    !$timeSlot['is_selected'],
                                'bg-purple-100 text-purple-800 border-purple-300 cursor-pointer hover:bg-purple-200' =>
                                    $timeSlot['is_available'] &&
                                    $timeSlot['slot_type'] === 'premium' &&
                                    !$timeSlot['is_selected'],
                                'bg-green-200 text-green-900 border-green-400 shadow-inner' =>
                                    $timeSlot['is_selected'] && $timeSlot['slot_type'] === 'free',
                                'bg-purple-200 text-purple-900 border-purple-400 shadow-inner' =>
                                    $timeSlot['is_selected'] && $timeSlot['slot_type'] === 'premium',
                            ])
                                @if ($timeSlot['is_available']) wire:click="toggleTimeSlot('{{ $timeSlot['slot_key'] }}')" @endif>
                                <div @class([
                                    'font-semibold',
                                    'text-xs' => $compactView,
                                    '' => !$compactView,
                                ])>{{ $timeSlot['start_time'] }}@if (!$compactView)
                                        - {{ $timeSlot['end_time'] }}
                                    @endif
                                </div>
                                @if ($timeSlot['is_past'])
                                    <div class="text-xs">
                                        @if ($compactView)
                                            -
                                        @else
                                            Past
                                        @endif
                                    </div>
                                @elseif($timeSlot['is_booked'])
                                    <div class="text-xs">Booked</div>
                                @elseif($timeSlot['is_selected'])
                                    <div class="text-xs">✓ @if (!$compactView)
                                            Selected
                                        @endif
                                    </div>
                                @elseif($timeSlot['is_available'])
                                    <div class="text-xs">{{ $timeSlot['slot_type'] === 'free' ? '🆓' : '⭐' }}
                                        @if (!$compactView)
                                            {{ $timeSlot['slot_type'] === 'free' ? ' Free' : ' Premium' }}
                                        @endif
                                    </div>
                                    @if ($timeSlot['is_peak'] && !$compactView)
                                        <div class="text-xs text-orange-600">💡 Lights required</div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Footer -->
                <div class="rounded-b-xl border-t border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        @if (!$compactView)
                            <div class="text-sm text-gray-600">
                                Click on available time slots to select them for booking
                            </div>
                        @endif
                        <button wire:click="closeTimeSelector" @class([
                            'bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors',
                            'px-3 py-1 text-sm' => $compactView,
                            'px-4 py-2' => !$compactView,
                        ])>
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Enhanced Date Picker Modal -->
    @if ($showDatePicker)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
            <div @class([
                'w-full transform rounded-xl bg-white shadow-2xl',
                'max-w-lg' => $compactView,
                'max-w-2xl' => !$compactView,
            ])>
                <!-- Header -->
                <div class="rounded-t-xl border-b border-gray-200 bg-gray-50 p-4">
                    <h3 @class([
                        'font-bold text-gray-800',
                        'text-sm' => $compactView,
                        'text-lg' => !$compactView,
                    ])>📅 Jump to Date</h3>

                    <!-- Date Picker Mode Selector -->
                    <div class="mt-3 flex gap-1 rounded-lg border bg-white p-1">
                        <button wire:click="setDatePickerMode('day')" @class([
                            'flex-1 rounded-md font-medium transition-all duration-200',
                            'px-2 py-1 text-xs' => $compactView,
                            'px-3 py-2 text-sm' => !$compactView,
                            'bg-blue-500 text-white' => $datePickerMode === 'day',
                            'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'day',
                        ])>
                            📅 Day
                        </button>
                        <button wire:click="setDatePickerMode('week')" @class([
                            'flex-1 rounded-md font-medium transition-all duration-200',
                            'px-2 py-1 text-xs' => $compactView,
                            'px-3 py-2 text-sm' => !$compactView,
                            'bg-blue-500 text-white' => $datePickerMode === 'week',
                            'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'week',
                        ])>
                            📅 Week
                        </button>
                        <button wire:click="setDatePickerMode('month')" @class([
                            'flex-1 rounded-md font-medium transition-all duration-200',
                            'px-2 py-1 text-xs' => $compactView,
                            'px-3 py-2 text-sm' => !$compactView,
                            'bg-blue-500 text-white' => $datePickerMode === 'month',
                            'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'month',
                        ])>
                            📅 Month
                        </button>
                    </div>
                </div>

                <!-- Month/Year Selectors -->
                <div class="border-b border-gray-200 p-4">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <label @class([
                                'block font-medium text-gray-700 mb-1',
                                'text-xs' => $compactView,
                                'text-sm' => !$compactView,
                            ])>Month</label>
                            <select wire:model.live="selectedMonth" @class([
                                'w-full rounded-lg border border-gray-300 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500',
                                'px-2 py-1 text-xs' => $compactView,
                                'px-3 py-2 text-sm' => !$compactView,
                            ])>
                                @foreach ($availableMonths as $value => $name)
                                    <option value="{{ $value }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1">
                            <label @class([
                                'block font-medium text-gray-700 mb-1',
                                'text-xs' => $compactView,
                                'text-sm' => !$compactView,
                            ])>Year</label>
                            <select wire:model.live="selectedYear" @class([
                                'w-full rounded-lg border border-gray-300 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500',
                                'px-2 py-1 text-xs' => $compactView,
                                'px-3 py-2 text-sm' => !$compactView,
                            ])>
                                @foreach ($availableYears as $year)
                                    <option value="{{ $year }}">{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Calendar Content -->
                <div @class([
                    'max-h-96 overflow-y-auto',
                    'p-2' => $compactView,
                    'p-4' => !$compactView,
                ])>
                    @if ($datePickerMode === 'day')
                        <!-- Day Picker -->
                        <div class="mb-2 grid grid-cols-7 gap-1">
                            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                                <div @class([
                                    'text-center font-medium text-gray-500',
                                    'p-1 text-xs' => $compactView,
                                    'p-2 text-xs' => !$compactView,
                                ])>
                                    {{ $compactView ? substr($dayName, 0, 1) : $dayName }}
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-7 gap-1">
                            @foreach ($calendarDays as $day)
                                <button wire:click="selectDate('{{ $day['date'] }}')" @class([
                                    'aspect-square rounded-lg transition-all duration-200 hover:scale-105',
                                    'p-1 text-xs' => $compactView,
                                    'p-2 text-sm' => !$compactView,
                                    'text-gray-900 hover:bg-blue-50 border border-transparent hover:border-blue-200' =>
                                        $day['is_current_month'] &&
                                        !$day['is_past'] &&
                                        !$day['is_today'] &&
                                        $day['booking_type'] === 'none',
                                    'text-gray-400 bg-gray-50' => !$day['is_current_month'] || $day['is_past'],
                                    'bg-blue-500 text-white font-bold' => $day['is_today'],
                                    'bg-green-100 text-green-800 border border-green-300 hover:bg-green-200' =>
                                        $day['can_book_free'] && !$day['is_today'],
                                    'bg-purple-100 text-purple-800 border border-purple-300 hover:bg-purple-200' =>
                                        $day['can_book_premium'] && !$day['is_today'] && !$day['can_book_free'],
                                    'cursor-pointer' => $day['is_current_month'],
                                    'cursor-not-allowed' => !$day['is_current_month'],
                                ])
                                    @disabled(!$day['is_current_month'])
                                    title="{{ $day['formatted_date'] }} - {{ $day['booking_type'] === 'free' ? 'Free Booking' : ($day['booking_type'] === 'premium' ? 'Premium Booking' : 'Not Available') }}">
                                    <div class="font-medium">{{ $day['day_number'] }}</div>
                                    @if ($day['can_book_free'])
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
                            @foreach ($calendarWeeks as $week)
                                <button wire:click="selectWeek('{{ $week['week_start'] }}')"
                                    @class([
                                        'w-full rounded-lg border text-left transition-all duration-200 hover:scale-105',
                                        'p-2' => $compactView,
                                        'p-3' => !$compactView,
                                        'bg-blue-100 border-blue-300 text-blue-800' => $week['is_current_week'],
                                        'bg-gray-100 border-gray-300 text-gray-500' => $week['is_past_week'],
                                        'bg-green-100 border-green-300 text-green-800 hover:bg-green-200' =>
                                            $week['can_book_free'] &&
                                            !$week['is_current_week'] &&
                                            !$week['is_past_week'],
                                        'bg-purple-100 border-purple-300 text-purple-800 hover:bg-purple-200' =>
                                            $week['can_book_premium'] &&
                                            !$week['can_book_free'] &&
                                            !$week['is_current_week'] &&
                                            !$week['is_past_week'],
                                        'bg-white border-gray-300 hover:bg-gray-50' =>
                                            !$week['is_bookable'] &&
                                            !$week['is_current_week'] &&
                                            !$week['is_past_week'],
                                    ])>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div @class([
                                                'font-medium',
                                                'text-sm' => $compactView,
                                                '' => !$compactView,
                                            ])>Week {{ $week['week_number'] }}</div>
                                            <div @class([
                                                'opacity-75',
                                                'text-xs' => $compactView,
                                                'text-sm' => !$compactView,
                                            ])>{{ $week['formatted_range'] }}</div>
                                        </div>
                                        <div class="text-right">
                                            @if ($week['is_current_week'])
                                                <span @class([
                                                    'bg-blue-200 px-2 py-1 rounded',
                                                    'text-xs' => $compactView || !$compactView,
                                                ])>Current</span>
                                            @elseif($week['is_past_week'])
                                                <span @class([
                                                    'bg-gray-200 px-2 py-1 rounded',
                                                    'text-xs' => $compactView || !$compactView,
                                                ])>Past</span>
                                            @elseif($week['can_book_free'])
                                                <span @class([
                                                    'bg-green-200 px-2 py-1 rounded',
                                                    'text-xs' => $compactView || !$compactView,
                                                ])>🆓 Free</span>
                                            @elseif($week['can_book_premium'])
                                                <span @class([
                                                    'bg-purple-200 px-2 py-1 rounded',
                                                    'text-xs' => $compactView || !$compactView,
                                                ])>⭐ Premium</span>
                                            @else
                                                <span @class([
                                                    'bg-gray-200 px-2 py-1 rounded',
                                                    'text-xs' => $compactView || !$compactView,
                                                ])>🔒 Locked</span>
                                            @endif
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @else
                        <!-- Month Picker -->
                        <div @class([
                            'grid gap-3',
                            'grid-cols-2' => $compactView,
                            'grid-cols-3' => !$compactView,
                        ])>
                            @foreach ($calendarMonths as $month)
                                <button wire:click="selectMonth('{{ $month['month_start'] }}')"
                                    @class([
                                        'rounded-lg border text-center transition-all duration-200 hover:scale-105',
                                        'p-2' => $compactView,
                                        'p-4' => !$compactView,
                                        'bg-blue-100 border-blue-300 text-blue-800' => $month['is_current_month'],
                                        'bg-gray-100 border-gray-300 text-gray-500' => $month['is_past_month'],
                                        'bg-green-100 border-green-300 text-green-800 hover:bg-green-200' =>
                                            $month['can_book_free'] &&
                                            !$month['is_current_month'] &&
                                            !$month['is_past_month'],
                                        'bg-purple-100 border-purple-300 text-purple-800 hover:bg-purple-200' =>
                                            $month['can_book_premium'] &&
                                            !$month['can_book_free'] &&
                                            !$month['is_current_month'] &&
                                            !$month['is_past_month'],
                                        'bg-white border-gray-300 hover:bg-gray-50' =>
                                            !$month['is_bookable'] &&
                                            !$month['is_current_month'] &&
                                            !$month['is_past_month'],
                                    ])>
                                    <div @class([
                                        'font-medium',
                                        'text-sm' => $compactView,
                                        '' => !$compactView,
                                    ])>{{ $month['month_name'] }}</div>
                                    <div @class(['mt-1', 'text-xs' => $compactView || !$compactView])>
                                        @if ($month['is_current_month'])
                                            Current
                                        @elseif($month['is_past_month'])
                                            Past
                                        @elseif($month['booking_type'] === 'mixed')
                                            🆓⭐ @if (!$compactView)
                                                Mixed
                                            @endif
                                        @elseif($month['can_book_free'])
                                            🆓 @if (!$compactView)
                                                Free
                                            @endif
                                        @elseif($month['can_book_premium'])
                                            ⭐ @if (!$compactView)
                                                Premium
                                            @endif
                                        @else
                                            🔒 @if (!$compactView)
                                                Locked
                                            @endif
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div class="rounded-b-xl border-t border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        @if (!$compactView)
                            <div class="text-xs text-gray-500">
                                <div class="flex items-center gap-4">
                                    <span class="flex items-center gap-1">
                                        <div class="h-3 w-3 rounded border border-green-300 bg-green-100"></div>
                                        🆓 Free
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <div class="h-3 w-3 rounded border border-purple-300 bg-purple-100"></div>
                                        ⭐ Premium
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <div class="h-3 w-3 rounded bg-gray-100"></div>
                                        🔒 Locked
                                    </span>
                                </div>
                            </div>
                        @endif
                        <button wire:click="closeDatePicker" @class([
                            'text-gray-600 hover:text-gray-800 transition-colors',
                            'px-3 py-1 text-sm' => $compactView,
                            'px-4 py-2' => !$compactView,
                        ])>
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showConfirmModal)
        <div class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="animate-scale-in mx-4 w-full max-w-lg transform rounded-xl bg-white p-6 shadow-2xl">
                <h3 class="mb-6 text-xl font-bold">
                    @if ($bookingType === 'mixed')
                        🎾 Mixed Booking Confirmation
                    @else
                        🎾 {{ ucfirst($bookingType) }} Booking Confirmation
                    @endif
                </h3>

                <div class="mb-6 space-y-4">
                    @foreach ($pendingBookingData as $booking)
                        <div class="rounded-lg border bg-gray-50 p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-semibold">{{ $booking['date']->format('l, F j, Y') }}</div>
                                    <div class="text-lg">{{ $booking['start_time'] . ' - ' . $booking['end_time'] }}
                                    </div>
                                    @if ($booking['is_light_required'])
                                        <div class="mt-1 text-sm text-orange-600">
                                            💡 additional IDR 50k/hour for tennis court lights
                                        </div>
                                    @endif
                                </div>
                                <span @class([
                                    'bg-blue-100 text-blue-800' => $booking['booking_type'] === 'free',
                                    'bg-purple-100 text-purple-800' => $booking['booking_type'] !== 'free',
                                    'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium',
                                ])
                                    @if ($booking['booking_type'] === 'free') 🆓
                            @else
                            ⭐ @endif
                                    {{ strtoupper($booking['booking_type']) }} </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-gray-600">
                    <p>💳 *Please process the payment to the Receptionist before using the tennis court</p>
                    <p>⚠️ *Please be responsible with your bookings. Failure to comply may result in being blacklisted.
                    </p>
                </div>

                <div class="flex justify-end gap-3">
                    <button class="rounded-lg px-6 py-2 text-gray-600 transition-colors hover:text-gray-800"
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

    <!-- Thank You Modal -->
    @if ($showThankYouModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div @class([
                'mx-4 w-full transform rounded-xl bg-white text-center shadow-2xl',
                'max-w-sm p-6' => $compactView,
                'max-w-md p-8' => !$compactView,
            ])>
                <div @class([
                    'mb-4',
                    'text-4xl' => $compactView,
                    'text-6xl' => !$compactView,
                ])>🎾</div>
                <h3 @class([
                    'mb-4 font-bold',
                    'text-lg' => $compactView,
                    'text-xl' => !$compactView,
                ])>Thank you for your booking!</h3>
                <div @class([
                    'mb-6 rounded-lg bg-gray-100 font-bold text-gray-800',
                    'py-2 text-xl' => $compactView,
                    'py-4 text-3xl' => !$compactView,
                ])>
                    #{{ $bookingReference }}
                </div>
                <button @class([
                    'transform rounded-lg bg-gradient-to-r from-gray-600 to-gray-800 text-white transition-all duration-300 hover:scale-105 hover:from-gray-700 hover:to-gray-900',
                    'px-6 py-2 text-sm' => $compactView,
                    'px-8 py-3' => !$compactView,
                ]) wire:click="closeModal">
                    🏠 @if ($compactView)
                        BACK
                    @else
                        BACK TO BOOKING
                    @endif
                </button>
            </div>
        </div>
    @endif

    <!-- Login Reminder Modal -->
    @if ($showLoginReminder)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div @class([
                'mx-4 w-full transform rounded-xl bg-white shadow-2xl',
                'max-w-sm p-4' => $compactView,
                'max-w-md p-6' => !$compactView,
            ])>
                <h3 @class([
                    'mb-4 font-bold',
                    'text-base' => $compactView,
                    'text-lg' => !$compactView,
                ])>🔐 Login Required</h3>
                <p @class([
                    'mb-6 text-gray-600',
                    'text-sm' => $compactView,
                    '' => !$compactView,
                ])>Please log in to your tenant account to proceed with booking.</p>
                <div class="flex justify-end gap-3">
                    <button @class([
                        'text-gray-600 transition-colors hover:text-gray-800',
                        'px-3 py-1 text-sm' => $compactView,
                        'px-4 py-2' => !$compactView,
                    ]) wire:click="closeModal">
                        Cancel
                    </button>
                    <button @class([
                        'rounded-lg bg-blue-600 text-white transition-colors hover:bg-blue-700',
                        'px-3 py-1 text-sm' => $compactView,
                        'px-4 py-2' => !$compactView,
                    ]) wire:click="redirectToLogin">
                        🔑 Login
                    </button>
                </div>
            </div>
        </div>
    @endif

    @include('components.toast')
</div>

@script
    <script>
        $js('showToast', (value) => {
            toast(value);
        })
    </script>
@endscript
