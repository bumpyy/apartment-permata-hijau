<?php

use App\Enum\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use App\Services\BookingValidationService;
use App\Settings\SiteSettings;
use App\Traits\HasBookingValidation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Polling;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;

new #[Layout('components.frontend.layouts.app')] class extends Component
{
    use HasBookingValidation;
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

    public $viewMode = 'weekly'; // Current view: 'weekly', 'monthly', or 'daily'

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

    // === SYSTEM STATE ===
    public $isRefreshing = false; // Whether system is refreshing booking data

    public $lastRefreshTime; // Timestamp of last refresh

    // === CONFLICT NOTIFICATIONS ===
    public $conflictNotifications = []; // Array of conflict notifications to show

    public $showConflictModal = false; // Show conflict resolution modal

    public $conflictDetails = []; // Details about conflicts for modal

    // === CROSS-COURT CONFLICT DETECTION ===
    public $crossCourtConflicts = []; // Array of cross-court conflicts

    public $showCrossCourtConflictModal = false; // Show cross-court conflict modal

    public $crossCourtConflictDetails = []; // Details about cross-court conflicts for modal

    public $crossCourtBookings = []; // Cached cross-court bookings for current view period

    public $crossCourtBookingsLoaded = false; // Whether cross-court bookings have been loaded

    // === PERFORMANCE OPTIMIZATION ===
    public $pollingInterval = 30000; // Polling interval in milliseconds

    public $isLazyLoaded = false; // Whether component has been lazy loaded

    // === SETTINGS INTEGRATION ===
    protected $siteSettings;

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

    public $whatsappNumber;

    /**
     * Initialize the component when it's first loaded
     * Sets up default values and loads initial data
     */
    public function mount(SiteSettings $siteSettings)
    {

        // Set default court (hardcoded to Court 2 for now)
        $this->courtNumber = request()->route('id');

        if (! $this->courtNumber || ! Court::find($this->courtNumber)) {
            return redirect()->route('facilities.tennis');
        }

        $whatsappNumber = preg_replace('/[^0-9]/', '', $siteSettings->whatsapp_number);

        // Initialize dates to today
        $this->selectedDate = now()->format('Y-m-d');
        $this->currentDate = now();
        $this->currentWeekStart = now()->startOfWeek()->addWeek(1);
        $this->currentMonthStart = now()->startOfMonth();

        // Set premium booking date using override if available, fallback to 25th
        $currentDate = now();
        $this->premiumBookingDate = \App\Models\PremiumDateOverride::getCurrentMonthPremiumDate();

        if ($currentDate->toDateString() > $this->premiumBookingDate->toDateString()) {
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
        $this->loadCrossCourtBookings();
        $this->generateTimeSlots();
        $this->generateWeekDaysForComponent();
        $this->generateMonthDaysForComponent();
        $this->initializeDatePicker();

        // Mark as loaded
        $this->isLazyLoaded = true;

        // Initialize polling based on site settings
        $this->initializePolling();
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
     * Computed property for quota info - cached and reactive
     */
    public function getQuotaInfoProperty()
    {
        return $this->getQuotaInfo();
    }

    /**
     * Computed property for current view title
     */
    public function getCurrentViewTitleProperty()
    {
        return match ($this->viewMode) {
            'weekly' => $this->currentWeekStart->format('M j').' - '.$this->currentWeekStart->copy()->addDays(6)->format('M j, Y'),
            'monthly' => $this->currentMonthStart->format('F Y'),
            'daily' => $this->currentDate->format('l, F j, Y'),
            default => $this->currentDate->format('l, F j, Y'),
        };
    }

    /**
     * Computed property for selected slots count
     */
    public function getSelectedSlotsCountProperty()
    {
        return count($this->selectedSlots);
    }

    /**
     * Computed property for booking type
     */
    public function getBookingTypeProperty()
    {
        if (empty($this->selectedSlots)) {
            return 'none';
        }

        $types = collect($this->selectedSlots)
            ->map(function ($slot) {
                return $this->getSlotType($slot);
            })
            ->unique()
            ->values();

        if ($types->count() > 1) {
            return 'mixed';
        }

        return $types->first() ?? 'none';
    }

    /**
     * Load cross-court bookings for the current view period
     * This prevents repeated database queries for conflict detection
     */
    public function loadCrossCourtBookings()
    {
        if (! $this->isLoggedIn || $this->crossCourtBookingsLoaded) {
            return;
        }

        // Check if cross-court conflict detection is enabled
        try {
            $siteSettings = app(\App\Settings\SiteSettings::class);
            if (! $siteSettings->isCrossCourtConflictDetectionEnabled()) {
                $this->crossCourtBookings = [];
                $this->crossCourtBookingsLoaded = true;

                return;
            }
        } catch (\Exception $e) {
            // If settings are not available, default to enabled
        }

        // Determine date range based on current view mode
        $startDate = $this->viewMode === 'weekly' ? $this->currentWeekStart : $this->currentMonthStart->copy()->startOfWeek();
        $endDate = $this->viewMode === 'weekly' ? $this->currentWeekStart->copy()->addWeek() : $this->currentMonthStart->copy()->endOfMonth()->endOfWeek();

        $tenantId = auth('tenant')->id();

        // Get all bookings for this tenant across all courts in the date range
        $bookings = Booking::where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->where('court_id', '!=', $this->courtNumber) // Exclude current court
            ->with('court:id,name')
            ->get(['id', 'court_id', 'date', 'start_time', 'end_time', 'booking_reference', 'status']);

        // Organize bookings by date and time for quick lookup
        $this->crossCourtBookings = [];
        foreach ($bookings as $booking) {
            $date = $booking->date->format('Y-m-d');
            $startTime = $booking->start_time->format('H:i');
            $endTime = $booking->end_time->format('H:i');

            if (! isset($this->crossCourtBookings[$date])) {
                $this->crossCourtBookings[$date] = [];
            }

            $this->crossCourtBookings[$date][] = [
                'id' => $booking->id,
                'court_name' => $booking->court->name ?? 'Unknown Court',
                'court_id' => $booking->court_id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'booking_reference' => $booking->booking_reference,
                'status' => $booking->status->value,
            ];
        }

        $this->crossCourtBookingsLoaded = true;
    }

    /**
     * Load booked and pending slots for the current view period
     * This populates the bookedSlots and preliminaryBookedSlots arrays
     */
    public function loadBookedSlots()
    {
        // Use cache key based on court, view mode, and date range
        $cacheKey = "booked_slots_{$this->courtNumber}_{$this->viewMode}_".($this->viewMode === 'weekly' ? $this->currentWeekStart->format('Y-m-d') : $this->currentMonthStart->format('Y-m'));

        // Try to get from cache first
        $cachedData = cache()->get($cacheKey);
        if ($cachedData && ! $this->isRefreshing) {
            $this->bookedSlots = $cachedData['booked'] ?? [];
            $this->preliminaryBookedSlots = $cachedData['pending'] ?? [];
            // Also load cross-court bookings when loading from cache
            $this->loadCrossCourtBookings();

            return;
        }

        // Determine date range based on current view mode
        $startDate = $this->viewMode === 'weekly' ? $this->currentWeekStart : $this->currentMonthStart->copy()->startOfWeek();
        $endDate = $this->viewMode === 'weekly' ? $this->currentWeekStart->copy()->addWeek() : $this->currentMonthStart->copy()->endOfMonth()->endOfWeek();

        // Get all bookings for this court in the date range with eager loading
        $bookings = Booking::where('court_id', $this->courtNumber)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->with(['tenant:id,name'])
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->get(['id', 'tenant_id', 'date', 'start_time', 'status']);

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

        // Cache the results for 30 seconds
        cache()->put(
            $cacheKey,
            [
                'booked' => $this->bookedSlots,
                'pending' => $this->preliminaryBookedSlots,
            ],
            30,
        );
    }

    /**
     * Called when selectedDate changes in daily view
     * Resets selections and regenerates available times
     */
    public function updatedSelectedDate()
    {
        // Use Livewire 3's built-in debouncing
        $this->dispatch('date-changed', $this->selectedDate);
    }

    /**
     * Handle date change with debouncing
     */
    public function handleDateChange()
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

        // Get available slots from service
        $availableSlots = $this->getAvailableTimeSlots($this->courtNumber, $targetDate);

        // Reset arrays
        $this->availableTimes = [];
        $this->availableTimesForDate = [];

        foreach ($availableSlots as $slot) {
            $isSelected = in_array($slot['slot_key'], $this->selectedSlots);

            // For daily view (simple array of available times)
            if (!$date) {
                if ($slot['is_available']) {
                    $this->availableTimes[] = $slot['start_time'];
                }
            }

            // For modal time selector (detailed slot information)
            $this->availableTimesForDate[] = [
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'slot_key' => $slot['slot_key'],
                'slot_type' => $slot['slot_type'],
                'is_available' => $slot['is_available'],
                'is_booked' => $slot['is_booked'],
                'is_selected' => $isSelected,
                'is_past' => $slot['is_past'],
                'is_peak' => $slot['is_peak'],
            ];
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
        if (!$this->isLoggedIn) {
            $this->showLoginReminder = true;
            return;
        }

        if (in_array($slotKey, $this->selectedSlots)) {
            // REMOVE SLOT: Simply remove from array
            $this->selectedSlots = array_diff($this->selectedSlots, [$slotKey]);
        } else {
            // ADD SLOT: Validate before adding
            $tenant = auth('tenant')->user();
            $tempSlots = array_merge($this->selectedSlots, [$slotKey]);
            $validationResult = $tenant->validateSlotSelection($tempSlots, $this->courtNumber);

            if (!$validationResult['can_book']) {
                // Show warnings
                if (!empty($validationResult['warnings'])) {
                    $this->quotaWarning = implode(' ', $validationResult['warnings']);
                    $this->js("toast('{$this->quotaWarning}',{type:'warning'})");
                }

                // Handle conflicts
                if (!empty($validationResult['conflicts'])) {
                    foreach ($validationResult['conflicts'] as $conflict) {
                        if (isset($conflict['conflicting_booking'])) {
                            // Cross-court conflict
                            $this->crossCourtConflictDetails = $validationResult['conflicts'];
                            $this->showCrossCourtConflictModal = true;
                        } else {
                            // Regular conflict - refresh data and show message
                            $this->quotaWarning = $conflict['message'];
                            $this->js("toast('{$this->quotaWarning}',{type:'warning',duration:5000})");

                            // Refresh available times to show updated status
                            $this->generateAvailableTimesForDate();
                            $this->loadBookedSlots();
                        }
                    }
                }
                return;
            }

            // Add the slot and clear any warnings
            $this->selectedSlots[] = $slotKey;
            $this->quotaWarning = '';
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

        $tenant = auth('tenant')->user();
        $validationResult = $tenant->validateSlotSelection($this->selectedSlots, $this->courtNumber);

        if (!$validationResult['can_book']) {
            $this->quotaWarning = implode(' ', $validationResult['warnings']);
            if (!empty($this->quotaWarning)) {
                $this->js("toast('{$this->quotaWarning}',{type:'warning'})");
            }
        } else {
            $this->quotaWarning = ''; // Clear any previous warnings
        }

        // Handle conflicts
        if (!empty($validationResult['conflicts'])) {
            foreach ($validationResult['conflicts'] as $conflict) {
                if (isset($conflict['conflicting_booking'])) {
                    // Cross-court conflict
                    $this->crossCourtConflictDetails = $validationResult['conflicts'];
                    $this->showCrossCourtConflictModal = true;
                } else {
                    // Regular conflict
                    $this->js("toast('{$conflict['message']}',{type:'warning',duration:5000})");
                }
            }
        }
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

        // Check for booking conflicts before showing confirmation
        if (! $this->validateSlotsStillAvailable()) {
            // Conflicts were found and slots were removed, don't show confirmation
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
                    'status' => BookingStatusEnum::CONFIRMED,
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
     * Creates database records with duplicate prevention
     */
    public function processBooking()
    {
        $tenant = auth('tenant')->user();

        // Final validation: Check if slots are still available before processing
        if (! $this->validateSlotsStillAvailable()) {
            // If there are conflicts, close the confirmation modal and show the warning
            $this->showConfirmModal = false;

            return;
        }

        // Generate a single booking reference for all bookings in this batch
        $this->bookingReference = Booking::generateBookingReference($tenant->id, $this->courtNumber);

        // Create each booking in the database with additional conflict checking
        foreach ($this->pendingBookingData as $slot) {
            try {
                // Double-check that this specific slot is still available
                if ($this->isSlotAlreadyBookedForComponent($slot['date']->format('Y-m-d'), $slot['start_time'])) {
                    // This slot was taken by someone else while we were processing
                    $this->quotaWarning = "⏰ Slot {$slot['date']->format('M j')} at {$slot['start_time']} was just booked by another tenant. Please refresh and try again.";
                    $this->js("toast('{$this->quotaWarning}',{type:'error',duration:6000})");

                    // Close confirmation modal and return
                    $this->showConfirmModal = false;

                    return;
                }

                // CREATE BOOKING RECORD with the same reference for all bookings
                $booking = Booking::create([...$slot, 'tenant_id' => $tenant->id, 'booking_reference' => $this->bookingReference]);
            } catch (\Exception $e) {
                // Log detailed error information for debugging
                Log::error('Booking creation failed: '.$e->getMessage(), [
                    'slot' => $slot,
                    'tenant_id' => $tenant->id,
                    'court_id' => $this->courtNumber,
                ]);

                $this->quotaWarning = 'Failed to create booking. Please try again.';

                // Close confirmation modal and return
                $this->showConfirmModal = false;

                return;
            }
        }

        // Reset state after successful booking
        $this->selectedSlots = [];
        $this->generateAvailableTimesForDate();
        $this->quotaInfo = $this->getQuotaInfo();
        $this->loadBookedSlots();
        $this->quotaWarning = '';

        // Clear cache for affected date ranges
        $this->clearBookingCache();

        // Show success notification
        $bookingCount = count($this->pendingBookingData);
        $successMessage = "🎾 Successfully created {$bookingCount} booking(s)! Reference: #{$this->bookingReference}";
        $this->js("toast('{$successMessage}',{type:'success',duration:8000})");

        // Flash success message
        session()->flash('message', 'Booking request sent successfully!');

        // Show thank you modal
        $this->showConfirmModal = false;
        $this->showThankYouModal = true;
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
    public function generateWeekDaysForComponent()
    {
        $this->weekDays = $this->generateWeekDays($this->currentWeekStart);
    }

    /**
     * Generate days for monthly view
     * Creates calendar grid including days from previous/next month
     */
    public function generateMonthDaysForComponent()
    {
        $this->monthDays = $this->generateMonthDays(
            $this->currentMonthStart,
            $this->bookedSlots,
            $this->preliminaryBookedSlots,
            $this->selectedSlots
        );
    }

    /**
     * Get booking counts for a specific date
     * Returns array with counts of different booking types
     *
     * @param  Carbon  $date  - Date to count bookings for
     * @return array - Counts of booked, pending, selected, available slots
     */
    public function getDateBookingCountsForComponent($date)
    {
        return $this->getDateBookingCounts($date, $this->courtNumber, $this->bookedSlots, $this->preliminaryBookedSlots, $this->selectedSlots);
    }

    // === BOOKING RULES FUNCTIONS ===
    // These functions determine when users can book slots

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
        // Reset cross-court bookings loaded flag for new view
        $this->crossCourtBookingsLoaded = false;
        // Reload booking data for new view
        $this->loadBookedSlots();
        // Generate appropriate data for the new view
        if ($mode === 'weekly') {
            $this->generateWeekDaysForComponent();
        } elseif ($mode === 'monthly') {
            $this->generateMonthDaysForComponent();
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
            $this->crossCourtBookingsLoaded = false;
            $this->loadBookedSlots();
            $this->generateWeekDaysForComponent();
        } elseif ($this->viewMode === 'monthly') {
            $this->currentMonthStart = $this->currentMonthStart->subMonth();
            $this->crossCourtBookingsLoaded = false;
            $this->loadBookedSlots();
            $this->generateMonthDaysForComponent();
        } elseif ($this->viewMode === 'daily') {
            $this->currentDate = $this->currentDate->subDay();
            $this->selectedDate = $this->currentDate->format('Y-m-d');
            $this->crossCourtBookingsLoaded = false;
            $this->loadBookedSlots();
            $this->generateAvailableTimesForDate();
        }
    }

    /**
     * Navigate to next period (week/month/day)
     */
    public function nextPeriod()
    {
        if ($this->viewMode === 'weekly') {
            $this->currentWeekStart = $this->currentWeekStart->addWeek();
            $this->crossCourtBookingsLoaded = false;
            $this->loadBookedSlots();
            $this->generateWeekDaysForComponent();
        } elseif ($this->viewMode === 'monthly') {
            $this->currentMonthStart = $this->currentMonthStart->addMonth();
            $this->crossCourtBookingsLoaded = false;
            $this->loadBookedSlots();
            $this->generateMonthDaysForComponent();
        } elseif ($this->viewMode === 'daily') {
            $this->currentDate = $this->currentDate->addDay();
            $this->selectedDate = $this->currentDate->format('Y-m-d');
            $this->crossCourtBookingsLoaded = false;
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
            $this->crossCourtBookingsLoaded = false;
            $this->loadBookedSlots();
            $this->generateWeekDaysForComponent();
        } elseif ($this->viewMode === 'monthly') {
            $this->crossCourtBookingsLoaded = false;
            $this->loadBookedSlots();
            $this->generateMonthDaysForComponent();
        } else {
            $this->crossCourtBookingsLoaded = false;
            $this->loadBookedSlots();
            $this->generateAvailableTimesForDate();
        }
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
        $this->showConflictModal = false;
        $this->showCrossCourtConflictModal = false;
    }

    /**
     * Close conflict modal and clear conflict details
     */
    public function closeConflictModal()
    {
        $this->showConflictModal = false;
        $this->conflictDetails = [];
    }

    /**
     * Close cross-court conflict modal and clear conflict details
     */
    public function closeCrossCourtConflictModal()
    {
        $this->showCrossCourtConflictModal = false;
        $this->crossCourtConflictDetails = [];
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
            $this->generateWeekDaysForComponent();
        } elseif ($this->viewMode === 'monthly') {
            $this->currentMonthStart = $selectedDate->startOfMonth();
            $this->generateMonthDaysForComponent();
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
            $this->generateWeekDaysForComponent();
        }

        $this->crossCourtBookingsLoaded = false;
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
            $this->generateMonthDaysForComponent();
        }

        $this->crossCourtBookingsLoaded = false;
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

    /**
     * Refresh booking data to get latest availability
     * This can be called periodically or when needed
     */
    public function refreshBookingData()
    {
        // Prevent multiple simultaneous refreshes
        if ($this->isRefreshing) {
            return;
        }

        $this->isRefreshing = true;

        try {
            // Store previous booking state to detect changes
            $previousBookedSlots = $this->bookedSlots;
            $previousPreliminarySlots = $this->preliminaryBookedSlots;

            // Reset cross-court bookings loaded flag to force refresh
            $this->crossCourtBookingsLoaded = false;

            // Force cache refresh by setting isRefreshing flag
            $this->loadBookedSlots();

            // Only regenerate view data if needed
            $this->regenerateViewData();

            // Check for conflicts in currently selected slots
            $this->validateSlotsStillAvailable();

            // Show notification if new bookings were detected
            $this->notifyNewBookings($previousBookedSlots, $previousPreliminarySlots);

            $this->lastRefreshTime = now()->format('H:i:s');

            // Show refresh success notification only for manual refreshes
            if (request()->has('manual_refresh')) {
                $this->js("toast('✅ Booking data refreshed successfully',{type:'success',duration:3000})");
            }
        } finally {
            $this->isRefreshing = false;
        }
    }

    /**
     * Regenerate view data based on current view mode
     */
    public function regenerateViewData()
    {
        if ($this->viewMode === 'daily') {
            $this->generateAvailableTimesForDate();
        } elseif ($this->viewMode === 'weekly') {
            $this->generateWeekDaysForComponent();
        } elseif ($this->viewMode === 'monthly') {
            $this->generateMonthDaysForComponent();
        }
    }

    /**
     * Manual refresh with notification
     */
    public function manualRefresh()
    {
        $this->refreshBookingData();
        $this->js("toast('✅ Booking data refreshed successfully',{type:'success',duration:3000})");
    }

    /**
     * Update polling interval based on user activity
     */
    public function updatePollingInterval($interval)
    {
        $this->pollingInterval = $interval;
    }

    /**
     * Initialize polling based on site settings
     */
    public function initializePolling()
    {
        try {
            $this->siteSettings = app(\App\Settings\SiteSettings::class);

            if ($this->siteSettings->isPollingEnabled()) {
                // Set initial polling interval based on device type
                $isMobile = request()->header('User-Agent') && (str_contains(request()->header('User-Agent'), 'Mobile') || str_contains(request()->header('User-Agent'), 'Android') || str_contains(request()->header('User-Agent'), 'iPhone'));

                $this->pollingInterval = $this->siteSettings->getPollingInterval(true, $isMobile);

                // Dispatch event to start polling
                $this->dispatch('start-polling', [
                    'interval' => $this->pollingInterval,
                    'inactivity_timeout' => $this->siteSettings->getInactivityTimeout(),
                ]);
            } else {
                // Polling is disabled
                $this->pollingInterval = 0;
                $this->dispatch('stop-polling');
            }
        } catch (\Exception $e) {
            // Fallback to default polling if settings are not available
            $this->pollingInterval = 30000;
            $this->dispatch('start-polling', [
                'interval' => $this->pollingInterval,
                'inactivity_timeout' => 300000,
            ]);
        }
    }

    /**
     * Check if polling is enabled
     */
    public function isPollingEnabled()
    {
        try {
            return app(\App\Settings\SiteSettings::class)->isPollingEnabled();
        } catch (\Exception $e) {
            return true; // Default to enabled if settings not available
        }
    }

    /**
     * Notify user about new bookings that appeared during refresh
     */
    public function notifyNewBookings($previousBookedSlots, $previousPreliminarySlots)
    {
        $newBookings = collect($this->bookedSlots)->filter(function ($slot) use ($previousBookedSlots) {
            return ! collect($previousBookedSlots)->contains('key', $slot['key']);
        });

        $newPreliminaryBookings = collect($this->preliminaryBookedSlots)->filter(function ($slot) use ($previousPreliminarySlots) {
            return ! collect($previousPreliminarySlots)->contains('key', $slot['key']);
        });

        $totalNewBookings = $newBookings->count() + $newPreliminaryBookings->count();

        if ($totalNewBookings > 0) {
            $message = "🆕 {$totalNewBookings} new booking(s) detected. Availability has been updated.";
            $this->js("toast('{$message}',{type:'info',duration:4000})");
        }
    }

    /**
     * Clear booking cache for affected date ranges
     */
    public function clearBookingCache()
    {
        // Clear cache for current month and adjacent months
        $months = [$this->currentMonthStart->copy()->subMonth(), $this->currentMonthStart, $this->currentMonthStart->copy()->addMonth()];

        foreach ($months as $month) {
            $cacheKey = "booked_slots_{$this->courtNumber}_monthly_".$month->format('Y-m');
            cache()->forget($cacheKey);
        }

        // Clear cache for current week and adjacent weeks
        $weeks = [$this->currentWeekStart->copy()->subWeek(), $this->currentWeekStart, $this->currentWeekStart->copy()->addWeek()];

        foreach ($weeks as $week) {
            $cacheKey = "booked_slots_{$this->courtNumber}_weekly_".$week->format('Y-m-d');
            cache()->forget($cacheKey);
        }
    }

    /**
     * Check if a specific time slot is already booked by anyone
     * This prevents duplicate bookings across all tenants
     *
     * @param  string  $date  - Date in Y-m-d format
     * @param  string  $startTime  - Start time in H:i format
     * @return bool - True if slot is already booked
     */
    public function isSlotAlreadyBookedForComponent($date, $startTime)
    {
        return Booking::isSlotBooked($this->courtNumber, $date, $startTime);
    }

    /**
     * Check for cross-court booking conflicts for the current tenant
     * This prevents tenants from booking multiple courts at the same time
     * Uses cached cross-court bookings for better performance
     *
     * @param  string  $date  - Date in Y-m-d format
     * @param  string  $startTime  - Start time in H:i format
     * @param  string  $endTime  - End time in H:i format
     * @return array - Array of conflicting bookings
     */
    public function checkCrossCourtConflictsForComponent($date, $startTime, $endTime)
    {
        if (! $this->isLoggedIn) {
            return [];
        }

        // Load cross-court bookings if not already loaded
        if (! $this->crossCourtBookingsLoaded) {
            $this->loadCrossCourtBookings();
        }

        // Check if cross-court conflict detection is enabled
        try {
            $siteSettings = app(\App\Settings\SiteSettings::class);
            if (! $siteSettings->isCrossCourtConflictDetectionEnabled()) {
                return [];
            }
        } catch (\Exception $e) {
            // If settings are not available, default to enabled
        }

        // If no cross-court bookings loaded, return empty array
        if (empty($this->crossCourtBookings)) {
            return [];
        }

        // Check for conflicts using cached data
        $conflicts = [];
        if (isset($this->crossCourtBookings[$date])) {
            foreach ($this->crossCourtBookings[$date] as $booking) {
                // Check for overlapping time slots
                $bookingStart = $booking['start_time'];
                $bookingEnd = $booking['end_time'];

                // Check if the new booking overlaps with existing booking
                if (
                    // New booking starts during existing booking
                    ($startTime >= $bookingStart && $startTime < $bookingEnd) ||
                    // New booking ends during existing booking
                    ($endTime > $bookingStart && $endTime <= $bookingEnd) ||
                    // New booking completely contains existing booking
                    ($startTime <= $bookingStart && $endTime >= $bookingEnd)
                ) {
                    $conflicts[] = $booking;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check for booking conflicts in the selected slots
     * Returns array of conflicting slots if any
     *
     * @return array - Array of conflicting slot keys
     */
    public function checkForBookingConflicts()
    {
        $conflicts = [];

        foreach ($this->selectedSlots as $slotKey) {
            $parts = explode('-', $slotKey);
            if (count($parts) >= 4) {
                $date = $parts[0].'-'.$parts[1].'-'.$parts[2];
                $startTime = count($parts) == 4 ? $parts[3] : $parts[3].':'.$parts[4];

                if ($this->isSlotAlreadyBookedForComponent($date, $startTime)) {
                    $conflicts[] = $slotKey;
                }
            }
        }

        return $conflicts;
    }

    /**
     * Validate that selected slots are still available before processing
     * This prevents race conditions where multiple users try to book the same slot
     *
     * @return bool - True if all slots are still available
     */
    public function validateSlotsStillAvailable()
    {
        $conflicts = $this->checkForBookingConflicts();

        if (! empty($conflicts)) {
            // Prepare conflict details for better UX
            $this->conflictDetails = collect($conflicts)
                ->map(function ($slot) {
                    $parts = explode('-', $slot);
                    $date = Carbon::createFromFormat('Y-m-d', $parts[0].'-'.$parts[1].'-'.$parts[2]);
                    $time = count($parts) == 4 ? $parts[3] : $parts[3].':'.$parts[4];
                    $endTime = Carbon::createFromFormat('H:i', $time)->addHour()->format('H:i');

                    return [
                        'slot_key' => $slot,
                        'date' => $date->format('l, F j, Y'),
                        'time' => $time,
                        'end_time' => $endTime,
                        'formatted_time' => $date->format('M j').' at '.$time,
                        'is_peak' => Carbon::createFromFormat('H:i', $time)->hour >= 18,
                    ];
                })
                ->toArray();

            // Remove conflicting slots from selection
            $this->selectedSlots = array_diff($this->selectedSlots, $conflicts);

            // Show conflict modal for better UX
            $this->showConflictModal = true;

            // Refresh available times
            $this->generateAvailableTimesForDate();
            $this->loadBookedSlots();

            return false;
        }

        return true;
    }
} ?>

<div>
    <!-- Header Component -->
    @include('livewire.tenant.booking.ui.header')

    <div class="container mx-auto min-h-screen bg-white px-4 py-6">
        <!-- Navigation Component -->
        @include('livewire.tenant.booking.ui.navigation')

        <!-- Booking Rules Component -->
        @include('livewire.tenant.booking.ui.booking-rules')

        <!-- Calendar Wrapper with Offline Overlay -->
        <div class="relative">
            <!-- Weekly View -->
            @if ($viewMode === 'weekly')
                @include('livewire.tenant.booking.views.weekly-view')
            @endif

            <!-- Monthly View -->
            @if ($viewMode === 'monthly')
                @include('livewire.tenant.booking.views.monthly-view')
            @endif

            <!-- Daily View -->
            @if ($viewMode === 'daily')
                @include('livewire.tenant.booking.views.daily-view')
            @endif

            <!-- Offline Overlay on Calendar -->
            <div
                wire:offline
                id="offline-calendar-overlay"
                class="absolute inset-0 z-10 min-h-[300px] w-full bg-white/80 backdrop-blur-sm rounded-lg shadow-lg transition-opacity duration-300 pointer-events-auto"
                role="alertdialog" aria-live="assertive"
            >
                <div class="size-full flex items-center justify-center">
                    <div class="flex flex-col items-center justify-center w-full max-w-md p-6">
                        <x-lucide-wifi-off id="offline-icon" class="w-14 h-14 text-red-500 mb-4" />
                        <div class="text-2xl font-bold text-red-600 mb-2">You are offline</div>
                        <div class="text-gray-700 mb-4 text-center">Calendar is unavailable while offline.<br>Please check your internet connection.</div>
                        <button class="px-4 py-2 bg-gray-400 text-white rounded cursor-not-allowed" disabled>Booking Unavailable</button>
                        <div class="mt-2 text-xs text-gray-500 text-center">The calendar will automatically refresh when you’re back online.</div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Selection Summary Component -->
        @include('livewire.tenant.booking.ui.selection-summary')

        <!-- Legend Component -->
        @include('livewire.tenant.booking.ui.legend')

        <!-- Confirm Button Component -->
        <div wire:offline.class="pointer-events-none opacity-50">
            @include('livewire.tenant.booking.ui.confirm-button')
        </div>
    </div>

    <!-- Time Selector Modal -->
    @include('livewire.tenant.booking.modals.time-selector')

    <!-- Enhanced Date Picker Modal -->
    @include('livewire.tenant.booking.modals.date-picker')

    <!-- Confirmation Modal -->
    @include('livewire.tenant.booking.modals.confirmation')

    <!-- Thank You Modal -->
    @include('livewire.tenant.booking.modals.thank-you')

    <!-- Login Reminder Modal -->
    @include('livewire.tenant.booking.modals.login-reminder')

    <!-- Cross Court Conflict Modal -->
    @include('livewire.tenant.booking.modals.cross-court-conflict')

    @include('components.toast')
</div>

@script
    <script>
        $js('showToast', (value) => {
            toast(value);
        })

        // Dynamic polling system based on site settings
        let pollingInterval;
        let userActivityTimeout;
        let isPollingEnabled = true;
        let currentInterval = 30000; // Default 30 seconds
        let inactivityTimeout = 300000; // Default 5 minutes

        // Start polling
        function startPolling() {
            if (!isPollingEnabled) return;

            pollingInterval = setInterval(() => {
                $wire.refreshBookingData();
            }, currentInterval);
        }

        // Stop polling
        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        // Update polling interval
        function updatePollingInterval(interval) {
            currentInterval = interval;
            if (isPollingEnabled) {
                stopPolling();
                startPolling();
            }
        }

        // Handle user activity
        function resetUserActivity() {
            clearTimeout(userActivityTimeout);
            userActivityTimeout = setTimeout(() => {
                // Reduce polling frequency when user is inactive
                if (isPollingEnabled) {
                    updatePollingInterval(60000); // 1 minute when inactive
                }
            }, inactivityTimeout);
        }

        function setActivePolling() {
            clearTimeout(userActivityTimeout);
            if (isPollingEnabled) {
                updatePollingInterval(30000); // 30 seconds when active
            }
            resetUserActivity();
        }

        // Listen for Livewire events
        $wire.$on('start-polling', (data) => {
            isPollingEnabled = true;
            currentInterval = data.interval || 30000;
            inactivityTimeout = data.inactivity_timeout || 300000;
            startPolling();
            resetUserActivity();
        });

        $wire.$on('stop-polling', () => {
            isPollingEnabled = false;
            stopPolling();
            clearTimeout(userActivityTimeout);
        });

        // Track user activity
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, setActivePolling, true);
        });

        // Initialize when component loads
        document.addEventListener('DOMContentLoaded', () => {
            // Check if polling should be enabled
            if ($wire.isPollingEnabled()) {
                $wire.dispatch('start-polling', {
                    interval: currentInterval,
                    inactivity_timeout: inactivityTimeout
                });
            }
        });

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopPolling();
            } else if (isPollingEnabled) {
                startPolling();
            }
        });

        // Animate the offline overlay and icon if anime.js is available
        document.addEventListener('livewire:offline', () => {
            const overlay = document.getElementById('offline-calendar-overlay');
            if (overlay && window.anime && window.anime.animate) {
                window.anime.animate(overlay, {
                    opacity: [0, 1],
                    duration: 500,
                    easing: 'easeOutQuad',
                });
                const icon = document.getElementById('offline-icon');
                if (icon) {
                    window.anime.animate(icon, {
                        scale: [0.8, 1.1, 1],
                        duration: 900,
                        direction: 'alternate',
                        loop: 2,
                        easing: 'easeInOutSine',
                    });
                }
            } else if (overlay) {
                overlay.style.opacity = 1;
            }
        });
        document.addEventListener('livewire:online', () => {
            const overlay = document.getElementById('offline-calendar-overlay');
            if (overlay) overlay.style.opacity = 0;
        });
    </script>
@endscript
