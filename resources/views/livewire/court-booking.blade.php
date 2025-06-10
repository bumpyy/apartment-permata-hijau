<?php

use Carbon\Carbon;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.frontend.app')] class extends Component {

    /**
     * @var int $courtNumber The number of the court for which we are booking.
     * This value is used to generate the booking reference.
     */
    public $courtNumber = 2;

    /**
     * @var \Carbon\Carbon|null $currentWeekStart The start date of the current week.
     * This value is used to generate the week days and time slots.
     */
    public $currentWeekStart = null;

    /**
     * @var \Carbon\Carbon|null $monthStart The start date of the current month.
     * This value is used to calculate the number of weeks in the month.
     */
    public $monthStart = null;

    /**
     * @var \Carbon\Carbon|null $monthEnd The end date of the current month.
     * This value is used to calculate the number of weeks in the month.
     */
    public $monthEnd = null;

    /**
     * @var string $startDate The start date of the week in the format 'Y-m-d'.
     * This value is used to display the week days and time slots.
     */
    public $startDate = '';

    /**
     * @var string $endDate The end date of the week in the format 'Y-m-d'.
     * This value is used to display the week days and time slots.
     */
    public $endDate = '';

    /**
     * @var array $weekDays An array of week days in the format [
     *  'name' => string,
     *  'date' => string,
     *  'day_number' => int,
     *  'month_name' => string,
     *  'is_today' => bool,
     *  'is_past' => bool,
     *  'is_this_week' => bool,
     *  'is_free_period' => bool,
     *  'formatted_date' => string,
     *  'days_from_now' => int,
     * ].
     * This value is used to display the week days and time slots.
     */
    public $weekDays = [];

    /**
     * @var array $timeSlots An array of time slots in the format [
     *  'start' => string,
     *  'end' => string,
     * ].
     * This value is used to display the week days and time slots.
     */
    public $timeSlots = [];

    /**
     * @var array $bookedSlots An array of booked slots in the format [
     *  'key' => string,
     *  'type' => string,
     * ].
     * This value is used to display the booked slots.
     */
    public $bookedSlots = [];

    /**
     * @var array $preliminaryBookedSlots An array of preliminary booked slots in the format [
     *  'key' => string,
     *  'type' => string,
     * ].
     * This value is used to display the preliminary booked slots.
     */
    public $preliminaryBookedSlots = [];

    /**
     * @var array $selectedSlots An array of selected slots in the format [
     *  'key' => string,
     * ].
     * This value is used to display the selected slots.
     */
    public $selectedSlots = [];

    /**
     * @var bool $showConfirmModal Whether to show the confirm modal.
     * This value is used to toggle the confirm modal.
     */
    public $showConfirmModal = false;

    /**
     * @var bool $showThankYouModal Whether to show the thank you modal.
     * This value is used to toggle the thank you modal.
     */
    public $showThankYouModal = false;

    /**
     * @var bool $showLoginReminder Whether to show the login reminder modal.
     * This value is used to toggle the login reminder modal.
     */
    public $showLoginReminder = false;

    /**
     * @var bool $showCalendarPicker Whether to show the calendar picker.
     * This value is used to toggle the calendar picker.
     */
    public $showCalendarPicker = false;

    /**
     * @var string $bookingReference The booking reference in the format 'C{court_number}-{year}-{month}-{day}-{hour}-{minute}'.
     * This value is used to generate the booking reference.
     */
    public $bookingReference = '';

    /**
     * @var array $pendingBookingData An array of pending booking data in the format [
     *  'date' => string,
     *  'time' => string,
     *  'is_light_required' => bool,
     *  'raw_date' => string,
     *  'raw_time' => string,
     *  'booking_type' => string,
     * ].
     * This value is used to display the pending booking data.
     */
    public $pendingBookingData = [];

    /**
     * @var string $bookingType The type of booking (free or premium).
     * This value is used to determine the quota for the selected booking slots.
     */
    public $bookingType = 'free';

    /**
     * @var array $quotaInfo An array of quota information in the format [
     *  'free' => [
     *      'remaining' => int,
     *  ],
     *  'premium' => [
     *      'remaining' => int,
     *  ],
     *  'combined' => [
     *      'remaining' => int,
     *  ],
     *  'weekly_remaining' => int,
     * ].
     * This value is used to display the quota information.
     */
    public $quotaInfo = [];

    /**
     * @var bool $canGoBack Whether the user can go back to the previous week.
     * This value is used to toggle the previous week button.
     */
    public $canGoBack = true;

    /**
     * @var bool $canGoForward Whether the user can go forward to the next week.
     * This value is used to toggle the next week button.
     */
    public $canGoForward = true;


    /**
     * @var int $numberOfWeeksInMonth The number of weeks in the current month.
     * This value is used to generate the week days and time slots.
     */
    public $numberOfWeeksInMonth = 0;

    /**
     * @var int $weekOffset The offset of the current week from the current date
     * in the format 'number of weeks'.
     * This value is used to generate the week days and time slots.
     */
    public $weekOffset = 0;

    /**
     * @var string $quotaWarning The quota warning message.
     * This value is used to display the quota warning message.
     */
    public $quotaWarning = '';

    /**
     * @var bool $isLoggedIn Whether the user is logged in.
     * This value is used to toggle the quota information.
     */
    public $isLoggedIn = false;

    /**
     * Initialize the component's state upon mounting.
     *
     * Sets the login status, calculates the start of the current week,
     * and updates week-related data. Loads quota information and retrieves
     * any pending booking slots from the session, updating selected slots
     * and determining the booking type if pending slots are found.
     */
    public function mount()
    {
        $this->isLoggedIn = auth('tenant')->check();
        $this->currentWeekStart = Carbon::today()->startOfWeek();
        $this->monthStart = Carbon::today()->startOfMonth();
        $this->monthEnd = Carbon::today()->endOfMonth();
        $this->numberOfWeeksInMonth = $this->monthStart->diffInWeeks($this->monthEnd);
        $this->weekOffset = $this->currentWeekStart->diffInWeeks($this->monthStart);

        $this->updateWeekData();
        $this->loadQuotaInfo();

        if (session()->has('pending_booking_slots')) {
            $this->selectedSlots = session('pending_booking_slots');
            session()->forget('pending_booking_slots');
            $this->determineBookingType();
        }
    }

    public function weeksInMonth($numOfDaysInMonth)
    {
        $daysInWeek = 7;
        $result = $numOfDaysInMonth / $daysInWeek;
        $numberOfFullWeeks = floor($result);
        $numberOfRemainingDays = ($result - $numberOfFullWeeks) * 7;
        return 'Weeks: ' . $numberOfFullWeeks . ' -  Days: ' . $numberOfRemainingDays;
    }

    /**
     * Updates the week data including start and end dates.
     *
     * This method sets the start and end dates of the current week,
     * generates the week days and time slots, loads booked slots for the week,
     * updates navigation state for week navigation, and validates the combined
     * quota for selected booking slots.
     */
    public function updateWeekData()
    {
        $weekStart = $this->currentWeekStart->copy();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $this->startDate = $weekStart->format('d/m/Y');
        $this->endDate = $weekEnd->format('d/m/Y');

        $this->generateWeekDays($weekStart);
        $this->generateTimeSlots();
        $this->loadBookedSlots();
        $this->updateNavigationState();
        $this->validateCombinedQuotaForSelections();
    }

    /**
     * Generate an array of week days starting from the given start date.
     *
     * Week days are represented as an array of objects with the following
     * properties:
     *  - name: The day of the week (e.g. MON, TUE, etc.)
     *  - date: The date of the day in the format Y-m-d
     *  - day_number: The day number of the month (e.g. 1, 2, 3, etc.)
     *  - month_name: The name of the month (e.g. January, February, etc.)
     *  - is_today: Whether the day is today
     *  - is_past: Whether the day is in the past (and not today)
     *  - is_free_period: Whether the day is in the free period (i.e. less than 7 days from now)
     *  - formatted_date: The date formatted as D, M j (e.g. Mon, Jan 2)
     *  - days_from_now: The number of days from now
     */
    public function generateWeekDays($startDate)
    {
        $days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
        $currentDate = $startDate->copy();
        $this->weekDays = [];

        for ($i = 0; $i < 7; $i++) {
            $isToday = $currentDate->isToday();
            $isPast = $currentDate->isPast() && !$isToday;
            $isThisWeek = $currentDate->isSameWeek(Carbon::now());
            $daysFromNow = Carbon::now()->diffInDays($currentDate, false);
            $isFreePeriod = $currentDate->isNextWeek();

            $this->weekDays[] = [
                'name' => $days[$i],
                'date' => $currentDate->format('Y-m-d'),
                'day_number' => $currentDate->format('j'),
                'month_name' => $currentDate->format('M'),
                'is_today' => $isToday,
                'is_past' => $isPast,
                'is_this_week' => $isThisWeek,
                'is_free_period' => $isFreePeriod,
                'formatted_date' => $currentDate->format('D, M j'),
                'days_from_now' => $daysFromNow,
            ];

            $currentDate->addDay();
        }
    }

    /**
     * Generate an array of time slots for the given week.
     *
     * Time slots are represented as an array of objects with the following
     * properties:
     *  - start: The start time of the slot in the format H:i
     *  - end: The end time of the slot in the format H:i
     */
    public function generateTimeSlots()
    {
        $this->timeSlots = [];
        for ($hour = 8; $hour < 23; $hour++) {
            $this->timeSlots[] = [
                'start' => sprintf('%02d:00', $hour),
                'end' => sprintf('%02d:00', $hour + 1),
            ];
        }
    }

    /**
     * Load booked slots for the current week.
     *
     * This method loads bookings for the current week and separates them into
     * two arrays: bookedSlots and preliminaryBookedSlots. bookedSlots contains
     * confirmed bookings, while preliminaryBookedSlots contains pending bookings.
     *
     * Each booking is represented as an array with two properties:
     *  - key: A string in the format 'Y-m-d-H:i' that identifies the booking
     *  - type: The type of booking (free or premium)
     */
    public function loadBookedSlots()
    {
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
                    'type' => $booking->booking_type,
                ];
            } else {
                $this->preliminaryBookedSlots[] = [
                    'key' => $slotKey,
                    'type' => $booking->booking_type,
                ];
            }
        }
    }

    /**
     * Updates the state of the navigation buttons (previous and next week).
     *
     * This method sets the properties $canGoBack and $canGoForward based on the
     * current week and the maximum number of future weeks that can be booked.
     */
    public function updateNavigationState()
    {
        $this->canGoBack = $this->currentWeekStart->gt($this->monthStart);
        $this->canGoForward = $this->weekOffset < $this->numberOfWeeksInMonth;
    }

    /**
     * Navigate to the previous week.
     *
     * This method checks if navigating to the previous week is allowed
     * by verifying the $canGoBack property. If allowed, it updates the
     * currentWeekStart to the previous week, decrements the weekOffset,
     * and refreshes the week data.
     */
    public function previousWeek()
    {
        if ($this->canGoBack) {
            $this->currentWeekStart = $this->currentWeekStart->subWeek();
            $this->weekOffset--;
            $this->updateWeekData();
        }
    }

    /**
     * Navigate to the next week.
     *
     * Checks if navigating to the next week is allowed by verifying the
     * $canGoForward property. If allowed, it updates the currentWeekStart
     * to the next week, increments the weekOffset, and refreshes the week data.
     */
    public function nextWeek()
    {
        if ($this->canGoForward) {
            $this->currentWeekStart = $this->currentWeekStart->addWeek();
            $this->weekOffset++;
            $this->updateWeekData();
        }
    }

    /**
     * Navigate to the current week.
     *
     * This method sets the currentWeekStart to the start of the current week,
     * resets the weekOffset to zero, and refreshes the week data.
     */
    public function goToCurrentWeek()
    {
        $this->currentWeekStart = Carbon::today()->startOfWeek();
        $this->weekOffset = 0;
        $this->updateWeekData();
    }

    /**
     * Navigate to a specific week relative to the current week.
     *
     * This method updates the currentWeekStart property to the specified week,
     * calculates the week offset from the current week, and refreshes the week data.
     *
     * @param int $weeksFromNow The number of weeks from the current week to jump to.
     */
    public function jumpToWeek($weeksFromNow)
    {
        $this->currentWeekStart = Carbon::today()->startOfWeek()->addWeeks($weeksFromNow);
        $this->weekOffset = $weeksFromNow;
        $this->updateWeekData();
    }

    /**
     * Shows the calendar picker.
     *
     * This method sets the showCalendarPicker property to true, which causes the
     * calendar picker to be displayed.
     */
    public function showCalendar()
    {
        $this->showCalendarPicker = true;
    }

    /**
     * Select a specific calendar week.
     *
     * This method sets the current week start date to the provided weekStart date,
     * calculates the week offset from the current week, hides the calendar picker,
     * and updates the week-related data.
     *
     * @param string $weekStart The start date of the week to select, in 'Y-m-d' format.
     */
    public function selectCalendarWeek($weekStart)
    {
        $this->currentWeekStart = Carbon::parse($weekStart)->startOfWeek();
        $this->weekOffset = Carbon::today()->startOfWeek()->diffInWeeks($this->currentWeekStart);
        $this->showCalendarPicker = false;
        $this->updateWeekData();
    }

    /**
     * Loads the tenant's quota information.
     *
     * This method loads the tenant's quotas for free, premium, and combined bookings,
     * as well as the remaining bookings for the current week.
     */
    public function loadQuotaInfo()
    {
        if (auth('tenant')->check()) {
            $tenant = auth('tenant')->user();
            $this->quotaInfo = [
                'free' => $tenant->free_booking_quota,
                'premium' => $tenant->premium_booking_quota,
                'combined' => $tenant->combined_booking_quota,
                'weekly_remaining' => $tenant->remaining_weekly_quota,
            ];
        }
    }

    /**
     * Validates the booking quota for selected slots.
     *
     * This method checks the number of selected free and premium booking slots
     * against the tenant's remaining quota for each type. If the number of selected
     * slots exceeds the remaining quota, a warning message is set. Otherwise, the
     * warning message is cleared. The validation considers slots within 7 days as
     * free and beyond 7 days as premium.
     */
    public function validateQuotaForSelections()
    {
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
    }

    /**
     * Validates the combined booking quota for selected slots.
     *
     * This method checks the total number of selected slots against the tenant's
     * remaining combined booking quota. If the number of selected slots exceeds
     * the remaining quota, a warning message is set. Otherwise, the warning message
     * is cleared. The validation considers all selected slots as contributing to
     * the combined booking quota.
     */
    public function validateCombinedQuotaForSelections()
    {
        if (!$this->isLoggedIn || empty($this->selectedSlots)) {
            $this->quotaWarning = '';
            return;
        }

        $selectedCount = count($this->selectedSlots);
        $remainingSlots = $this->quotaInfo['combined']['remaining'] ?? 0;

        if ($selectedCount > $remainingSlots) {
            $this->quotaWarning = "You've selected {$selectedCount} slots but only have {$remainingSlots} remaining out of your limit of 3.";
        } else {
            $this->quotaWarning = '';
        }
    }

    /**
     * Toggle a time slot in the selected slots array.
     *
     * This method will not add a time slot to the selected slots array if it is
     * already booked or pending, or if the time slot is in the past. If the time
     * slot is already in the selected slots array, it will be removed.
     *
     * @param string $slotKey A string in the format Y-m-d-H:i representing the time slot to toggle.
     */
    public function toggleTimeSlot($slotKey)
    {
        $bookedKeys = array_column($this->bookedSlots, 'key');
        $preliminaryKeys = array_column($this->preliminaryBookedSlots, 'key');

        if (in_array($slotKey, $bookedKeys) || in_array($slotKey, $preliminaryKeys)) {
            return;
        }

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
        $this->validateCombinedQuotaForSelections();
    }

    /**
     * Determines the booking type for the selected time slots.
     *
     * This method checks all selected time slots and determines if the booking
     * type is 'free' or 'mixed'. If all selected slots are within the current
     * week, the booking type is 'free'. If any selected slots are after the current
     * week, the booking type is 'mixed'.
     */
    public function determineBookingType()
    {
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
    }

    /**
     * Determine the booking type for the given time slot key.
     *
     * Given a time slot key in the format Y-m-d-H:i, this method returns
     * 'free' if the date is within 7 days from now, or 'premium' if the date
     * is after 7 days from now.
     *
     * @param string $slotKey A string in the format Y-m-d-H:i representing the time slot to check.
     * @return string The booking type for the given time slot, either 'free' or 'premium'.
     */
    public function getSlotType($slotKey)
    {
        $parts = explode('-', $slotKey);
        if (count($parts) >= 3) {
            $date = $parts[0] . '-' . $parts[1] . '-' . $parts[2];
            $daysFromNow = Carbon::now()->diffInDays(Carbon::parse($date), false);
            return $daysFromNow <= 7 ? 'free' : 'premium';
        }
        return 'free';
    }


    /**
     * Confirm a booking and open the confirmation modal.
     *
     * This method will not do anything if no time slots are selected. If the user
     * is not logged in, it will store the selected slots in the session and show
     * a login reminder. If the user has exceeded their booking quota, it will
     * show an error message. Otherwise, it will prepare the booking data and
     * show the confirmation modal.
     */
    public function confirmBooking()
    {
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
    }


    /**
     * Prepares the booking data for selected time slots.
     *
     * This method initializes the pending booking data array and iterates over
     * the selectedSlots array, parsing each slot key into date and time components.
     * For each valid slot, it calculates whether light is required based on the
     * start time, determines the booking type as either 'free' or 'premium' based
     * on the date, and formats the information into an array which is added to
     * pendingBookingData. Errors in parsing are logged.
     */
    public function prepareBookingData()
    {
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
    }


    /**
     * Process the booking for the selected time slots.
     *
     * This method processes the booking for each time slot in pendingBookingData.
     * It creates a new booking and sets the booking type and light requirement based
     * on the date and time of the booking. If the booking is successfully created,
     * its price is calculated and saved. If there are any errors, they are logged.
     * If the booking is successfully created, it sets the booking reference to the
     * same value for all bookings and updates them. Finally, it resets all modal
     * flags to false, closes all modals, and reloads booked slots and quota info.
     */
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
        /*************  ✨ Windsurf Command ⭐  *************/
        /**
         * Resets all modal flags to false, effectively closing all modals.
         */
        /*******  f29b7573-eb2a-48cb-b90e-3e620803f85a  *******/
    }


    /**
     * Resets all modal flags to false, effectively closing all modals.
     */
    public function closeModal()
    {
        $this->showConfirmModal = false;
        $this->showThankYouModal = false;
        $this->showLoginReminder = false;
        $this->showCalendarPicker = false;
    }

    /**
     * Redirects the user to the login page.
     *
     * This method returns a redirect response that navigates
     * the user to the login route.
     *
     * @return \Illuminate\Http\RedirectResponse The redirect response to the login route.
     */
    public function redirectToLogin()
    {
        return redirect()->route('login');
    }
}; ?>

<section>

    {{
        \Carbon\Carbon::now()->daysInMonth;
    }}

    <!-- Header -->
    <div class="relative overflow-hidden bg-gradient-to-r from-gray-600 to-gray-800 py-8 text-center text-white">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="relative z-10">
            <h1 class="text-3xl font-bold tracking-wide">🎾 TENNIS COURT BOOKING</h1>
            <p class="mt-2 text-gray-200">Reserve your perfect playing time</p>
        </div>
    </div>

    <!-- Content -->
    <div class="container min-h-screen bg-white py-6">
        <!-- Title Section -->
        <div class="mb-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="booking-title">
                    <h2 class="mb-2 text-lg font-medium text-gray-600">Select Date & Time</h2>
                    <h3 class="mb-2 text-3xl font-bold text-gray-800">
                        @if ($bookingType === 'mixed')
                        Mixed Booking, Court {{ $courtNumber }}
                        @else
                        {{ ucfirst($bookingType) }} Booking, Court {{ $courtNumber }}
                        @endif
                    </h3>
                </div>
                <div class="text-right">
                    <p class="text-sm font-medium text-gray-600">{{ $startDate }} - {{ $endDate }}</p>
                    @if ($weekOffset === 0)
                    <p class="text-xs font-bold text-blue-600">📅 Current Week</p>
                    @elseif($weekOffset === 1)
                    <p class="text-xs font-bold text-purple-600">📅 Next Week</p>
                    @else
                    <p class="text-xs font-bold text-purple-600">📅 {{ $weekOffset }} weeks ahead</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Login Prompt for Quota -->
        @if (!$isLoggedIn)
        <div class="mb-6 rounded-r-lg border-l-4 border-blue-400 bg-blue-50 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-700">
                        <strong>Login to see your booking quota</strong> and make reservations.
                        <a class="underline transition-colors hover:text-blue-900" href="{{ route('login') }}">Sign
                            in here</a>
                    </p>
                </div>
            </div>
        </div>
        @endif

        <!-- Week Navigation -->
        <div class="mb-8">
            <div
                class="flex flex-wrap items-center justify-between gap-y-6 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 p-6 shadow-sm">
                <div class="flex flex-wrap items-center gap-4">
                    <button
                        class="nav-button @if ($canGoBack) bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 text-gray-700 cursor-pointer shadow-sm hover:shadow-md
                            @else
                                bg-gray-200 border border-gray-200 text-gray-400 cursor-not-allowed @endif flex transform items-center gap-2 rounded-lg px-4 py-2 transition-all duration-300 hover:scale-105"
                        wire:click="previousWeek" @disabled(!$canGoBack)>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                            </path>
                        </svg>
                        Previous
                    </button>

                    @if ($weekOffset > 0)
                    <button
                        class="transform rounded-lg bg-blue-100 px-4 py-2 text-blue-700 shadow-sm transition-all duration-300 hover:scale-105 hover:bg-blue-200"
                        wire:click="goToCurrentWeek">
                        📅 Current Week
                    </button>
                    @endif

                    <button
                        class="transform rounded-lg bg-purple-100 px-4 py-2 text-purple-700 shadow-sm transition-all duration-300 hover:scale-105 hover:bg-purple-200"
                        wire:click="showCalendar">
                        📅 Pick Date
                    </button>
                </div>

                <!-- Quick Jump Buttons -->
                <div class="flex max-sm:flex-col">
                    <span class="mr-2 text-sm font-medium text-gray-600">Quick Jump:</span>
                    <div class="flex flex-wrap items-center gap-2">
                        @for ($i = 0; $i <= 4; $i++)
                            @php
                            $jumpDate=\Carbon\Carbon::today()->startOfWeek()->addWeeks($i);
                            $isCurrentWeek = $i === $weekOffset;
                            @endphp
                            <button
                                class="quick-jump @if ($isCurrentWeek) bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md
                                    @else
                                        bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 shadow-sm hover:shadow-md @endif transform rounded-full px-3 py-2 text-xs transition-all duration-300 hover:scale-110"
                                wire:click="jumpToWeek({{ $i }})"
                                title="{{ $jumpDate->format('M j') }} - {{ $jumpDate->copy()->addDays(6)->format('M j') }}">
                                @if ($i === 0)
                                This Week
                                @elseif($i === 1)
                                Next Week
                                @else
                                +{{ $i }}w
                                @endif
                            </button>
                            @endfor
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button
                        class="nav-button @if ($canGoForward) bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400 text-gray-700 cursor-pointer shadow-sm hover:shadow-md
                            @else
                                bg-gray-200 border border-gray-200 text-gray-400 cursor-not-allowed @endif flex transform items-center gap-2 rounded-lg px-4 py-2 transition-all duration-300 hover:scale-105"
                        wire:click="nextWeek" @disabled(!$canGoForward)>
                        Next
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Quota Info -->
        @if ($isLoggedIn && !empty($quotaInfo))
        <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
            <div
                class="quota-card rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-blue-100 p-6 shadow-sm transition-all duration-300 hover:shadow-md">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="font-bold text-blue-800">🆓 Free Booking Quota</h4>
                    <div class="rounded-full bg-blue-200 px-2 py-1 text-xs text-blue-800">Weekly</div>
                </div>
                <p class="mb-2 text-3xl font-bold text-blue-600">
                    {{ $quotaInfo['free']['used'] }}/{{ $quotaInfo['free']['total'] }}
                </p>
                <p class="text-sm text-blue-600">Up to 7 days ahead</p>

            </div>

            <div
                class="quota-card rounded-xl border border-purple-200 bg-gradient-to-br from-purple-50 to-purple-100 p-6 shadow-sm transition-all duration-300 hover:shadow-md">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="font-bold text-purple-800">⭐ Premium Booking Quota</h4>
                    <div class="rounded-full bg-purple-200 px-2 py-1 text-xs text-purple-800">Monthly</div>
                </div>
                <p class="mb-2 text-3xl font-bold text-purple-600">
                    {{ $quotaInfo['premium']['used'] }}/{{ $quotaInfo['premium']['total'] }}
                </p>
                <p class="text-sm text-purple-600">Up to 1 month ahead</p>

            </div>

            <div
                class="quota-card rounded-xl border border-green-200 bg-gradient-to-br from-green-50 to-green-100 p-6 shadow-sm transition-all duration-300 hover:shadow-md">
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="font-bold text-green-800">📊 Combined Quota</h4>
                    <div class="rounded-full bg-green-200 px-2 py-1 text-xs text-green-800">Available</div>
                </div>
                <p class="mb-2 text-3xl font-bold text-green-600">{{ $quotaInfo['combined']['remaining'] }}</p>
                <p class="text-sm text-green-600">Your remaining quota</p>
            </div>
        </div>
        @endif

        <!-- Quota Warning -->
        @if ($quotaWarning)
        <div class="quota-warning mb-6 rounded-r-lg border-l-4 border-orange-400 bg-orange-50 p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
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
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            {{ session('error') }}
        </div>
        @endif

        <!-- Booking Table -->
        <div class="booking-table-container mb-8 overflow-x-auto rounded-xl border border-gray-300 shadow-lg">
            <table class="w-full border-collapse bg-white">
                <thead>
                    <tr>
                        @foreach ($weekDays as $day)
                        <th @class([ 'border-r border-gray-300 last:border-r-0 p-4 text-center relative' , 'bg-gradient-to-b from-blue-500 to-blue-600 text-white'=> $day['is_today'],
                            'bg-gradient-to-b from-gray-400 to-gray-500 text-white' => $day['is_past'],
                            'bg-gradient-to-b from-blue-700 to-blue-800 text-white' =>
                            $day['is_free_period'],
                            'bg-gradient-to-b from-purple-600 to-purple-700 text-white' =>
                            !$day['is_today'] && !$day['is_past'] && !$day['is_free_period'],
                            ])>
                            <div class="flex flex-col items-center">
                                <div class="text-sm font-bold">{{ $day['name'] }}</div>
                                <div class="text-2xl font-bold">{{ $day['day_number'] }}</div>
                                <div class="text-xs opacity-90">{{ $day['month_name'] }}</div>
                                @if ($day['is_today'])
                                <div class="mt-1 rounded-full bg-blue-400 px-2 py-0.5 text-xs">


                                    TODAY</div>
                                @elseif(!$day['is_free_period'] && !$day['is_past'])
                                <div class="mt-1 rounded-full bg-purple-500 px-2 py-0.5 text-xs">PREMIUM</div>
                                @endif
                            </div>
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($timeSlots as $slot)
                    <tr
                        class="border-b border-gray-200 transition-colors duration-200 last:border-b-0 hover:bg-gray-50">
                        @foreach ($weekDays as $day)
                        @php
                        $slotKey = $day['date'] . '-' . $slot['start'];
                        $slotType = $this->getSlotType($slotKey);

                        $bookedSlot = collect($bookedSlots)->firstWhere('key', $slotKey);
                        $preliminarySlot = collect($preliminaryBookedSlots)->firstWhere('key', $slotKey);

                        $isBooked = $bookedSlot !== null;
                        $isPreliminary = $preliminarySlot !== null;
                        $isSelected = in_array($slotKey, $selectedSlots);

                        $slotDateTime = \Carbon\Carbon::createFromFormat(
                        'Y-m-d H:i',
                        $day['date'] . ' ' . $slot['start'],
                        );
                        $isPastSlot = $slotDateTime->isPast();
                        @endphp
                        <td class="time-slot @if ($isPastSlot) bg-gray-100 text-gray-400 cursor-not-allowed
                                        @elseif($isBooked)
                                            @if ($bookedSlot['type'] === 'free')
                                                bg-red-100 text-red-800 cursor-not-allowed border-l-4 border-red-400
                                            @else
                                                bg-red-200 text-red-900 cursor-not-allowed border-l-4 border-red-600 @endif @elseif($isPreliminary) @if ($preliminarySlot['type'] === 'free') bg-blue-100 text-blue-800 cursor-not-allowed border-l-4 border-blue-400
                                            @else
                                                bg-blue-200 text-blue-900 cursor-not-allowed border-l-4 border-blue-600 @endif @elseif($isSelected) @if ($slotType === 'free') bg-green-100 text-green-800 cursor-pointer hover:bg-green-200 transform scale-95 shadow-inner border-l-4 border-green-500
                                            @else
                                                bg-purple-100 text-purple-800 cursor-pointer hover:bg-purple-200 transform scale-95 shadow-inner border-l-4 border-purple-500 @endif @else @if ($slotType === 'free') cursor-pointer hover:bg-blue-50 hover:shadow-md transform hover:scale-105
                                            @else
                                                cursor-pointer hover:bg-purple-50 hover:shadow-md transform hover:scale-105 @endif @endif relative cursor-pointer border-r border-gray-200 p-3 text-center text-sm transition-all duration-300 last:border-r-0"
                            wire:click="toggleTimeSlot('{{ $slotKey }}')"
                            title="@if ($isPastSlot) Past slot @else {{ $day['formatted_date'] }} {{ $slot['start'] }}-{{ $slot['end'] }} ({{ ucfirst($slotType) }}) @endif">
                            <div class="py-1 font-bold">
                                {{ $slot['start'] }}
                            </div>
                            <div class="text-xs opacity-75">
                                {{ $slot['end'] }}
                            </div>

                            @if ($isPastSlot)
                            <div class="mt-1 text-xs text-gray-400">Past</div>
                            @elseif($isSelected)
                            <div
                                class="@if ($slotType === 'free') text-green-700 @else text-purple-700 @endif mt-1 text-xs font-bold">
                                ✓ Selected
                            </div>
                            @elseif($isBooked || $isPreliminary)
                            <div class="mt-1 text-xs font-bold">
                                @if ($isBooked) Booked
                                @else
                                Pending @endif
                            </div>
                            @else
                            <div class="mt-1 text-xs opacity-60">
                                @if ($slotType === 'free') 🆓 Free
                                @else
                                ⭐ Premium @endif
                            </div>
                            @endif

                            @if ($slotType === 'premium' && !$isPastSlot && !$isBooked && !$isPreliminary)
                            <div class="absolute right-1 top-1 h-2 w-2 rounded-full bg-purple-500"></div>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div
            class="mb-8 flex flex-wrap items-center gap-6 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 p-6 text-sm">
            <div class="flex items-center gap-2">
                <div class="h-4 w-4 rounded border-l-4 border-red-400 bg-red-100"></div>
                <span class="font-medium">🆓 Free Booked</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-4 rounded border-l-4 border-red-600 bg-red-200"></div>
                <span class="font-medium">⭐ Premium Booked</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-4 rounded border-l-4 border-blue-400 bg-blue-100"></div>
                <span class="font-medium">🆓 Free Pending</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-4 rounded border-l-4 border-blue-600 bg-blue-200"></div>
                <span class="font-medium">⭐ Premium Pending</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-4 rounded border-l-4 border-green-500 bg-green-100"></div>
                <span class="font-medium">🆓 Free Selected</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="h-4 w-4 rounded border-l-4 border-purple-500 bg-purple-100"></div>
                <span class="font-medium">⭐ Premium Selected</span>
            </div>
            <div class="ml-auto max-w-md text-xs italic text-gray-600">
                *For booking later than 6pm additional IDR 50k/hour will be charged for tennis court lights
            </div>
        </div>

        <!-- Selection Summary -->
        @if (count($selectedSlots) > 0)
        <div
            class="selection-summary mb-8 rounded-xl border border-green-200 bg-gradient-to-r from-green-50 to-blue-50 p-6 shadow-sm">
            <h4 class="mb-4 flex items-center gap-2 font-bold text-gray-800">
                🎯 Selected Time Slots ({{ count($selectedSlots) }})
                @if ($bookingType === 'mixed')
                <span
                    class="rounded-full bg-gradient-to-r from-blue-500 to-purple-500 px-2 py-1 text-xs text-white">Mixed
                    Booking</span>
                @endif
            </h4>
            <div class="flex flex-wrap gap-3">
                @foreach ($selectedSlots as $slot)
                @php
                $parts = explode('-', $slot);
                if (count($parts) >= 4) {
                $date = \Carbon\Carbon::createFromFormat(
                'Y-m-d',
                $parts[0] . '-' . $parts[1] . '-' . $parts[2],
                );
                $time = $parts[3];
                $slotType = $this->getSlotType($slot);
                }
                @endphp
                @if (isset($date) && isset($time))
                <span
                    class="selected-slot @if ($slotType === 'free') bg-gradient-to-r from-green-100 to-green-200 text-green-800 border border-green-300
                                @else
                                    bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 border border-purple-300 @endif inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition-all duration-300 hover:scale-105">
                    @if ($slotType === 'free')
                    🆓
                    @else
                    ⭐
                    @endif
                    {{ $date->format('M j') }} at {{ $time }}
                    <button
                        class="@if ($slotType === 'free') text-green-600 hover:text-green-800 @else text-purple-600 hover:text-purple-800 @endif ml-2 transition-transform duration-200 hover:scale-110"
                        wire:click="toggleTimeSlot('{{ $slot }}')">
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
                class="confirm-booking @if (count($selectedSlots) === 0) bg-gray-300 text-gray-500 cursor-not-allowed
                    @elseif($quotaWarning)
                        bg-orange-400 text-white cursor-not-allowed
                    @else
                        bg-gradient-to-r from-gray-700 via-gray-800 to-gray-900 text-white hover:from-gray-800 hover:via-gray-900 hover:to-black cursor-pointer hover:shadow-xl @endif transform rounded-xl px-8 py-4 text-sm font-bold shadow-lg transition-all duration-500 hover:scale-105"
                wire:click="confirmBooking" @disabled(count($selectedSlots)===0 || $quotaWarning)>
                @if ($quotaWarning)
                ⚠️ QUOTA EXCEEDED
                @else
                🎾 CONFIRM
                @if ($bookingType === 'mixed')
                MIXED
                @else
                {{ strtoupper($bookingType) }}
                @endif
                BOOKING(S)
                @if (count($selectedSlots) > 0)
                ({{ count($selectedSlots) }})
                @endif
                @endif
            </button>
        </div>
    </div>

    <!-- Calendar Picker Modal -->
    @if ($showCalendarPicker)
    <div class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="animate-scale-in mx-4 w-full max-w-md transform rounded-xl bg-white p-6 shadow-2xl">
            <h3 class="mb-6 text-center text-xl font-bold">📅 Select Week</h3>

            <div class="max-h-64 space-y-3 overflow-y-auto">

                @for ($i = 0; $i < $numOfWeeks; $i++)
                    @php
                    $weekStart=\Carbon\Carbon::today()->startOfWeek();
                    $weekEnd = $weekStart->copy()->endOfWeek();
                    $isCurrentWeek = $i === $weekOffset;
                    @endphp
                    <button
                        @class(['bg-blue-100 border-blue-300 text-blue-800'=> $isCurrentWeek,
                        'bg-gray-50 border-gray-200 hover:bg-gray-100' => !$isCurrentWeek,
                        'w-full rounded-lg border p-4 text-left transition-all duration-300 hover:scale-105' => true,
                        ])
                        wire:click="selectCalendarWeek('{{ $weekStart->format('Y-m-d') }}')">
                        <div class="font-semibold">
                            @if ($i === 0)
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

            <div class="mt-6 flex justify-end">
                <button class="px-6 py-2 text-gray-600 transition-colors hover:text-gray-800"
                    wire:click="closeModal">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    @endif

    <!-- Other modals remain the same... -->
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
                            <div class="font-semibold">{{ $booking['date'] }}</div>
                            <div class="text-lg">{{ $booking['time'] }}</div>
                            @if ($booking['is_light_required'])
                            <div class="mt-1 text-sm text-orange-600">
                                💡 additional IDR 50k/hour for tennis court lights
                            </div>
                            @endif
                        </div>
                        <span
                            class="@if ($booking['booking_type'] === 'free') bg-blue-100 text-blue-800 @else bg-purple-100 text-purple-800 @endif inline-flex items-center rounded-full px-3 py-1 text-xs font-medium">
                            @if ($booking['booking_type'] === 'free')
                            🆓
                            @else
                            ⭐
                            @endif
                            {{ strtoupper($booking['booking_type']) }}
                        </span>
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

    @if ($showThankYouModal)
    <div class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div
            class="animate-scale-in mx-4 w-full max-w-md transform rounded-xl bg-white p-8 text-center shadow-2xl">
            <div class="mb-4 text-6xl">🎾</div>
            <h3 class="mb-4 text-xl font-bold">Thank you for your booking!</h3>
            <div class="mb-6 rounded-lg bg-gray-100 py-4 text-3xl font-bold text-gray-800">
                #{{ $bookingReference }}</div>
            <button
                class="transform rounded-lg bg-gradient-to-r from-gray-600 to-gray-800 px-8 py-3 text-white transition-all duration-300 hover:scale-105 hover:from-gray-700 hover:to-gray-900"
                wire:click="closeModal">
                🏠 BACK TO BOOKING
            </button>
        </div>
    </div>
    @endif

    @if ($showLoginReminder)
    <div class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="animate-scale-in mx-4 w-full max-w-md transform rounded-xl bg-white p-6 shadow-2xl">
            <h3 class="mb-4 text-lg font-bold">🔐 Login Required</h3>
            <p class="mb-6 text-gray-600">Please log in to your tenant account to proceed with the booking.</p>
            <div class="flex justify-end gap-3">
                <button class="px-4 py-2 text-gray-600 transition-colors hover:text-gray-800"
                    wire:click="closeModal">
                    Cancel
                </button>
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-white transition-colors hover:bg-blue-700"
                    wire:click="redirectToLogin">
                    🔑 Login
                </button>
            </div>
        </div>
    </div>
    @endif
</section>
