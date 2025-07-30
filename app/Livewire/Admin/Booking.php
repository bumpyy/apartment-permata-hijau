<?php

namespace App\Livewire\Admin;

use App\Enum\BookingStatusEnum;
use App\Models\Booking as BookingModel;
use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class Booking extends Component
{
    use WithPagination;

    // Properties for filtering and search
    public $search = '';

    public $statusFilter = '';

    public $dateFilter = '';

    public $courtFilter = '';

    public $excludeCancelled = true;

    // Properties for detail panel
    public $showDetailPanel = false;

    public $selectedBooking = null;

    // Properties for editing
    public $editingBooking = null;

    public $showEditModal = false;

    // Form properties for editing
    public $editForm = [
        'status' => '',
        'is_light_required' => false,
        'notes' => '',
    ];

    public $viewMode = 'table'; // 'table' or 'weekly'

    public $tableTab = 'active'; // 'active' or 'past'

    public $weekStart;

    public $weekPicker = '';

    // Cache properties for optimization
    protected $cachedBookings = null;

    protected $cachedStats = null;

    protected $cachedCourts = null;

    // New Booking Modal properties
    public $showAddModal = false;

    public $addForm = [
        'court_id' => '',
        'tenant_id' => '',
        'date' => '',
        'time_slot' => '',
        'notes' => '',
    ];

    public $addError = '';

    public $availableTimeSlots = [];

    public $tenants = [];

    public $isAddMode = false;

    public $panelAddForm = [
        'court_id' => '',
        'date' => '',
        'start_time' => '',
        'end_time' => '',
        'tenant_id' => '',
        'notes' => '',
    ];

    public $panelAvailableCourts = [];

    public $panelTenants = [];

    public $panelAddError = '';

    // Cancellation modal properties
    public $showCancelModal = false;

    public $bookingToCancel = null;

    public $cancellationReason = '';

    // Today's bookings properties
    public $showTodaysBookings = true;

    // Export properties
    public $showExportModal = false;

    public $exportFormat = 'excel'; // 'excel' or 'pdf'

    public $exportDateFrom = '';

    public $exportDateTo = '';

    public $exportStatusFilter = '';

    public $exportCourtFilter = '';

    public $exportBookingTypeFilter = '';

    public $isExporting = false;

    public function mount()
    {
        $this->weekStart = now()->startOfWeek()->format('Y-m-d');
        $this->weekPicker = $this->weekStart;
    }

    public function updated($property)
    {
        // Reset pagination when any filter changes
        if (in_array($property, ['search', 'statusFilter', 'dateFilter', 'courtFilter', 'excludeCancelled', 'tableTab'])) {
            $this->refreshBookingData();
        }
    }

    public function showDetail($bookingId)
    {
        // Always fetch fresh data to ensure we have the latest booking information
        $this->selectedBooking = BookingModel::with([
            'tenant',
            'court',
            'approver',
            'canceller',
            'editor',
        ])->find($bookingId);

        $this->showDetailPanel = true;
    }

    public function closeDetailPanel()
    {
        $this->showDetailPanel = false;
    }

    public function edit($bookingId)
    {
        // Always fetch fresh data to ensure we have the latest booking information
        $booking = BookingModel::find($bookingId);

        if ($booking) {
            $this->editingBooking = $booking;
            $this->editForm = [
                'status' => $booking->status->value,
                'is_light_required' => $booking->is_light_required,
                'notes' => $booking->notes ?? '',
            ];
            $this->showEditModal = true;
        }
    }

    public function updateBooking()
    {
        $this->validate([
            'editForm.status' => 'required|in:pending,confirmed,cancelled',
        ]);

        if ($this->editingBooking) {
            $this->editingBooking->update([
                'status' => $this->editForm['status'],
                'is_light_required' => $this->editForm['is_light_required'],
                'notes' => $this->editForm['notes'],
                'edited_by' => auth('admin')->id(),
                'edited_at' => now(),
            ]);

            $this->showEditModal = false;
            $this->editingBooking = null;
            $this->reset('editForm');

            // Refresh booking data with updated booking
            $this->refreshBookingData($this->editingBooking);

            session()->flash('message', 'Booking updated successfully.');
            $this->dispatch('close-edit-modal');
        }
    }

    public function confirmBooking($bookingId)
    {
        $booking = BookingModel::find($bookingId);
        if ($booking) {
            $booking->update([
                'status' => 'confirmed',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
            ]);

            // Refresh booking data with updated booking
            $this->refreshBookingData($booking);

            session()->flash('message', 'Booking confirmed successfully.');
        }
    }

    public function openCancelModal($bookingId)
    {
        $booking = BookingModel::find($bookingId);
        if ($booking) {
            $this->bookingToCancel = $booking;
            $this->showCancelModal = true;
        }
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

        $this->bookingToCancel->update([
            'status' => 'cancelled',
            'cancelled_by' => auth('admin')->id(),
            'cancelled_at' => now(),
            'cancellation_reason' => $this->cancellationReason ?: 'Cancelled by admin',
        ]);

        // Refresh booking data with updated booking
        $this->refreshBookingData($this->bookingToCancel);

        session()->flash('message', 'Booking cancelled successfully.');
        $this->closeCancelModal();
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingBooking = null;
        $this->reset('editForm');

        // Refresh data to ensure UI is up to date
        $this->refreshBookingData();
    }

    public function getBookingsProperty()
    {
        if ($this->cachedBookings === null) {
            $this->cachedBookings = $this->buildBookingsQuery()->paginate(15);
        }

        return $this->cachedBookings;
    }

    public function getTodaysBookingsProperty()
    {
        return BookingModel::with(['tenant', 'court'])
            ->whereDate('date', Carbon::today())
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->orderBy('start_time')
            ->get()
            ->groupBy('court.name');
    }

    public function getUpcomingBookingsProperty()
    {
        return BookingModel::with(['tenant', 'court'])
            ->where('date', '>', Carbon::today())
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(10)
            ->get()
            ->groupBy(function ($booking) {
                return $booking->date->format('Y-m-d');
            });
    }

    public function getTotalBookingsProperty()
    {
        if ($this->cachedStats === null) {
            $this->cacheStats();
        }

        return $this->cachedStats['total'];
    }

    public function getActiveBookingsProperty()
    {
        if ($this->cachedStats === null) {
            $this->cacheStats();
        }

        return $this->cachedStats['confirmed'];
    }

    public function getPendingBookingsProperty()
    {
        if ($this->cachedStats === null) {
            $this->cacheStats();
        }

        return $this->cachedStats['pending'];
    }

    public function getCancelledBookingsProperty()
    {
        if ($this->cachedStats === null) {
            $this->cacheStats();
        }

        return $this->cachedStats['cancelled'];
    }

    public function getPastBookingsCountProperty()
    {
        return BookingModel::where('date', '<', now()->startOfDay())->count();
    }

    public function getActiveBookingsCountProperty()
    {
        return BookingModel::where('date', '>=', now()->startOfDay())->count();
    }

    public function getCourtsProperty()
    {
        if ($this->cachedCourts === null) {
            $this->cachedCourts = Court::orderBy('name')->get();
        }

        return $this->cachedCourts;
    }

    public function prevWeek()
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->startOfWeek()->format('Y-m-d');
        $this->refreshBookingData();
    }

    public function nextWeek()
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->startOfWeek()->format('Y-m-d');
        $this->refreshBookingData();
    }

    public function goToToday()
    {
        $this->weekStart = now()->startOfWeek()->format('Y-m-d');
        $this->weekPicker = $this->weekStart;
        $this->refreshBookingData();
    }

    public function updatedWeekPicker()
    {
        if ($this->weekPicker) {
            $this->weekStart = Carbon::parse($this->weekPicker)->startOfWeek()->format('Y-m-d');
            $this->refreshBookingData();
        }
    }

    public function getWeeklyBookingsProperty()
    {
        $startOfWeek = Carbon::parse($this->weekStart);
        $endOfWeek = $startOfWeek->copy()->endOfWeek();

        // Get bookings for the week with eager loading
        $bookings = $this->buildBookingsQuery()
            ->whereBetween('date', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')])
            ->get();

        // Pre-generate time slots
        $timeSlots = $this->generateTimeSlots();

        // Pre-generate week structure
        $grouped = $this->generateWeekStructure($startOfWeek, $endOfWeek, $timeSlots);

        // Assign bookings to time slots efficiently
        $this->assignBookingsToSlots($bookings, $grouped, $timeSlots);

        return [
            'days' => $grouped,
            'timeSlots' => array_map(fn ($slot) => [
                'start_time' => $slot[0],
                'end_time' => $slot[1],
                'label' => $slot[0].' - '.$slot[1],
            ], $timeSlots),
            'startOfWeek' => $startOfWeek,
            'endOfWeek' => $endOfWeek,
        ];
    }

    public function filterByCourt($courtId)
    {
        $this->courtFilter = $courtId;
        $this->refreshBookingData();
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    public function setTableTab($tab)
    {
        $this->tableTab = $tab;
        $this->refreshBookingData();
    }

    public function getGroupedBookingsProperty()
    {
        return $this->bookings->getCollection()->groupBy(function ($booking) {
            return $booking->date->format('Y-m-d');
        })->map(function ($group) {
            return $group->groupBy(function ($booking) {
                return $booking->court->name ?? 'Unknown Court';
            })->sortKeys();
        })->sortKeys();
    }

    // Private helper methods for optimization
    private function buildBookingsQuery()
    {
        $query = BookingModel::with(['tenant', 'court'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'asc');

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('tenant', function ($subQ) {
                    $subQ->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                })->orWhere('booking_reference', 'like', '%'.$this->search.'%');
            });
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', BookingStatusEnum::from($this->statusFilter));
        }

        // Apply court filter
        if ($this->courtFilter) {
            $query->where('court_id', $this->courtFilter);
        }

        // Apply date filter
        if ($this->dateFilter) {
            $query->whereDate('date', $this->dateFilter);
        }

        // Apply exclude cancelled filter
        if ($this->excludeCancelled) {
            $query->where('status', '!=', BookingStatusEnum::CANCELLED);
        }

        // Apply tab filter (active vs past bookings)
        if ($this->tableTab === 'active') {
            // Show future and today's bookings
            $query->where('date', '>=', now()->startOfDay());
        } else {
            // Show past bookings
            $query->where('date', '<', now()->startOfDay());
        }

        return $query;
    }

    private function cacheStats()
    {
        $this->cachedStats = BookingModel::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled
        ', [
            BookingStatusEnum::CONFIRMED->value,
            BookingStatusEnum::PENDING->value,
            BookingStatusEnum::CANCELLED->value,
        ])->first()->toArray();
    }

    private function clearCache()
    {
        $this->cachedBookings = null;
        $this->cachedStats = null;
        $this->cachedCourts = null;

        // Force refresh of computed properties
        $this->dispatch('$refresh');
    }

    /**
     * Refresh booking data and update UI after any booking operation
     */
    private function refreshBookingData($booking = null)
    {
        // Clear all caches
        $this->clearCache();

        // Update selected booking if provided
        if ($booking) {
            $this->selectedBooking = $booking->fresh(['tenant', 'court', 'approver', 'canceller', 'editor']);
        }

        // Reset pagination to show updated data
        $this->resetPage();

        // Update available time slots if in add mode
        if ($this->showAddModal || $this->isAddMode) {
            $this->updateAvailableTimeSlots();
        }

        // Dispatch events for UI updates
        $this->dispatch('booking-updated');
        $this->dispatch('refresh-weekly-view');
    }

    private function generateTimeSlots()
    {
        $slots = [];
        for ($hour = 8; $hour <= 22; $hour++) {
            $start = sprintf('%02d:00', $hour);
            $end = sprintf('%02d:00', $hour + 1);
            $slots[] = [$start, $end];
        }

        return $slots;
    }

    private function generateWeekStructure($startOfWeek, $endOfWeek, $timeSlots)
    {
        $grouped = [];
        $current = $startOfWeek->copy();

        while ($current <= $endOfWeek) {
            $dayKey = $current->format('Y-m-d');
            $grouped[$dayKey] = [];
            foreach ($timeSlots as $slot) {
                $slotLabel = $slot[0].' - '.$slot[1];
                $grouped[$dayKey][$slotLabel] = [];
            }
            $current->addDay();
        }

        return $grouped;
    }

    private function assignBookingsToSlots($bookings, &$grouped, $timeSlots)
    {
        foreach ($bookings as $booking) {
            $day = $booking->date->format('Y-m-d');
            $bookingStart = $booking->start_time->format('H:i');
            $bookingEnd = $booking->end_time->format('H:i');

            foreach ($timeSlots as $slot) {
                $slotStart = $slot[0];
                $slotEnd = $slot[1];

                // Check if booking overlaps with this time slot
                if ($bookingStart < $slotEnd && $bookingEnd > $slotStart) {
                    $slotLabel = $slotStart.' - '.$slotEnd;
                    $grouped[$day][$slotLabel][] = $booking;
                }
            }
        }

        // Sort bookings within each time slot by court name
        if (! $this->courtFilter) {
            foreach ($grouped as $day => $slots) {
                foreach ($slots as $slotLabel => $bookings) {
                    $grouped[$day][$slotLabel] = collect($bookings)->sortBy(function ($booking) {
                        return $booking->court->name ?? 'Unknown Court';
                    })->values();
                }
            }
        }
    }

    public function openAddModal()
    {
        $this->showAddModal = true;
        $this->addForm = [
            'court_id' => '',
            'tenant_id' => '',
            'date' => '',
            'time_slot' => '',
            'notes' => '',
        ];
        $this->addError = '';
        $this->tenants = \App\Models\Tenant::where('is_active', true)->orderBy('name')->get();
        $this->updateAvailableTimeSlots();
    }

    public function closeAddModal()
    {
        $this->showAddModal = false;

        // Reset form and refresh data
        $this->reset('addForm');
        $this->addError = '';
        $this->availableTimeSlots = [];

        // Refresh data to ensure UI is up to date
        $this->refreshBookingData();
    }

    public function updatedAddForm($field)
    {
        if (in_array($field, ['court_id', 'date'])) {
            $this->updateAvailableTimeSlots();
        }
    }

    public function updateAvailableTimeSlots()
    {
        if (! $this->addForm['court_id'] || ! $this->addForm['date']) {
            $this->availableTimeSlots = [];

            return;
        }

        $bookings = BookingModel::where('court_id', $this->addForm['court_id'])
            ->where('date', $this->addForm['date'])
            ->where('status', '!=', 'cancelled')
            ->get();

        $bookedSlots = $bookings->pluck('start_time')->toArray();
        $allSlots = $this->generateTimeSlots();
        $selectedDate = \Carbon\Carbon::parse($this->addForm['date']);
        $isToday = $selectedDate->isToday();

        $this->availableTimeSlots = collect($allSlots)
            ->filter(function ($slot) use ($bookedSlots, $isToday) {
                // Filter out booked slots
                if (in_array($slot[0], $bookedSlots)) {
                    return false;
                }

                // Filter out past time slots for today
                if ($isToday && $slot[0] <= now()->format('H:i')) {
                    return false;
                }

                return true;
            })
            ->map(function ($slot) use ($selectedDate) {
                $bookingType = $this->getDateBookingType($selectedDate);
                $isPeak = \Carbon\Carbon::createFromFormat('H:i', $slot[0])->hour >= 18;

                return [
                    'value' => $slot[0].'-'.$slot[1],
                    'label' => $slot[0].' - '.$slot[1],
                    'booking_type' => $bookingType,
                    'is_peak' => $isPeak,
                    'type_label' => $bookingType === 'free' ? '🆓 Free' : '⭐ Premium',
                    'peak_label' => $isPeak ? '💡 Lights' : '',
                ];
            })
            ->values()
            ->toArray();
    }

    public function createBooking()
    {
        $this->addError = '';
        $this->validate([
            'addForm.court_id' => 'required|exists:courts,id',
            'addForm.tenant_id' => 'required|exists:tenants,id',
            'addForm.date' => 'required|date|after_or_equal:today',
            'addForm.time_slot' => 'required',
        ]);

        $tenant = \App\Models\Tenant::find($this->addForm['tenant_id']);
        if (! $tenant) {
            $this->addError = 'Invalid tenant.';

            return;
        }

        $bookingDate = \Carbon\Carbon::parse($this->addForm['date']);
        if ($bookingDate->isPast()) {
            $this->addError = 'Cannot book on a past date.';

            return;
        }
        [$start, $end] = explode('-', $this->addForm['time_slot']);
        // Check again for slot conflict
        $exists = BookingModel::where('court_id', $this->addForm['court_id'])
            ->where('date', $this->addForm['date'])
            ->where('start_time', $start)
            ->where('status', '!=', 'cancelled')
            ->exists();
        if ($exists) {
            $this->addError = 'Selected time slot is already booked.';
            $this->updateAvailableTimeSlots();

            return;
        }

        // Check for cross-court conflicts if enabled
        try {
            $siteSettings = app(\App\Settings\SiteSettings::class);
            if ($siteSettings->isCrossCourtConflictDetectionEnabled()) {
                $crossCourtConflicts = BookingModel::getCrossCourtConflicts(
                    $this->addForm['tenant_id'],
                    $this->addForm['date'],
                    $start,
                    $end,
                    $this->addForm['court_id']
                );
                if (! empty($crossCourtConflicts)) {
                    $conflictDetails = collect($crossCourtConflicts)->map(function ($conflict) {
                        return "{$conflict['court_name']} at {$conflict['start_time']}-{$conflict['end_time']} (Ref: #{$conflict['booking_reference']})";
                    })->implode(', ');
                    $this->addError = "Cross-court conflict detected. Tenant already has bookings: {$conflictDetails}";
                    $this->updateAvailableTimeSlots();

                    return;
                }
            }
        } catch (\Exception $e) {
            // If settings are not available, continue without cross-court conflict detection
        }
        // Prevent booking in the past
        if ($this->addForm['date'] === now()->format('Y-m-d') && $start <= now()->format('H:i')) {
            $this->addError = 'Cannot book a past time slot.';
            $this->updateAvailableTimeSlots();

            return;
        }

        // Determine booking type
        $bookingType = $this->getDateBookingType($bookingDate);
        if ($bookingType === 'none') {
            $this->addError = 'Booking is not allowed for this date.';

            return;
        }
        // Check if premium booking is open if type is premium
        if ($bookingType === 'premium' && ! $this->isPremiumBookingOpen()) {
            $this->addError = 'Premium booking is not open yet.';

            return;
        }
        // Enforce tenant quota rules
        $quotaCheck = $tenant->canMakeSpecificTypeBooking($this->addForm['date'], $bookingType, 1);
        if (! $quotaCheck['can_book']) {
            $this->addError = $quotaCheck['reason'] ?? 'Tenant quota exceeded.';

            return;
        }
        // Enforce max 2 slots per day
        $existingBookingsForDate = BookingModel::where('tenant_id', $tenant->id)
            ->where('date', $this->addForm['date'])
            ->where('status', '!=', 'cancelled')
            ->count();
        if ($existingBookingsForDate >= 2) {
            $this->addError = 'Maximum 2 hours per day allowed for this tenant.';

            return;
        }
        // Enforce max 3 distinct days per week
        $weekStart = $bookingDate->copy()->startOfWeek()->format('Y-m-d');
        $distinctDays = BookingModel::where('tenant_id', $tenant->id)
            ->where('booking_week_start', $weekStart)
            ->where('status', '!=', 'cancelled')
            ->distinct('date')
            ->count('date');
        if ($distinctDays >= 3 && ! BookingModel::where('tenant_id', $tenant->id)->where('date', $this->addForm['date'])->exists()) {
            $this->addError = 'Tenant cannot book for more than 3 distinct days in a week.';

            return;
        }
        // Proceed with booking
        $booking = BookingModel::create([
            'tenant_id' => $this->addForm['tenant_id'],
            'court_id' => $this->addForm['court_id'],
            'date' => $this->addForm['date'],
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'confirmed',
            'booking_type' => $bookingType,
            'booking_week_start' => $bookingDate->copy()->startOfWeek()->format('Y-m-d'),
            'notes' => $this->addForm['notes'],
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);
        $booking->calculatePrice();
        $booking->booking_reference = $booking->generateReference();
        $booking->save();
        $this->showAddModal = false;

        // Refresh booking data with new booking
        $this->refreshBookingData($booking);

        session()->flash('message', 'Booking created successfully! Reference: #'.$booking->booking_reference);
    }

    public function startAddBooking($date, $startTime, $endTime)
    {
        // Prevent booking for past dates/times
        if (! $this->canBookSlot($date, $startTime)) {
            $this->panelAddError = 'Cannot book past dates or times.';

            return;
        }

        $this->isAddMode = true;
        $this->showDetailPanel = true;
        $this->panelAddForm = [
            'court_id' => '',
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'tenant_id' => '',
            'notes' => '',
        ];
        $this->panelAvailableCourts = \App\Models\Court::orderBy('name')->get()->map(function ($court) use ($date, $startTime) {
            $isBooked = BookingModel::where('court_id', $court->id)
                ->where('date', $date)
                ->where('start_time', $startTime)
                ->where('status', '!=', 'cancelled')
                ->exists();

            return [
                'id' => $court->id,
                'name' => $court->name,
                'is_booked' => $isBooked,
            ];
        });
        $this->panelTenants = \App\Models\Tenant::where('is_active', true)->orderBy('name')->get();
        $this->panelAddError = '';
    }

    public function cancelAddBooking()
    {
        $this->isAddMode = false;
        $this->showDetailPanel = false;
        $this->panelAddError = '';

        // Reset panel form
        $this->reset('panelAddForm');
        $this->panelAvailableCourts = [];
        $this->panelTenants = [];

        // Refresh data to ensure UI is up to date
        $this->refreshBookingData();
    }

    public function createBookingFromPanel()
    {
        $this->panelAddError = '';
        $this->validate([
            'panelAddForm.court_id' => 'required|exists:courts,id',
            'panelAddForm.tenant_id' => 'required|exists:tenants,id',
            'panelAddForm.date' => 'required|date|after_or_equal:today',
            'panelAddForm.start_time' => 'required',
            'panelAddForm.end_time' => 'required',
        ]);

        $tenant = \App\Models\Tenant::find($this->panelAddForm['tenant_id']);
        if (! $tenant) {
            $this->panelAddError = 'Invalid tenant.';

            return;
        }
        $bookingDate = \Carbon\Carbon::parse($this->panelAddForm['date']);
        if ($bookingDate->isPast()) {
            $this->panelAddError = 'Cannot book on a past date.';

            return;
        }
        if ($bookingDate->isToday() && $this->panelAddForm['start_time'] <= now()->format('H:i')) {
            $this->panelAddError = 'Cannot book a past time slot for today.';

            return;
        }
        $exists = BookingModel::where('court_id', $this->panelAddForm['court_id'])
            ->where('date', $this->panelAddForm['date'])
            ->where('start_time', $this->panelAddForm['start_time'])
            ->where('status', '!=', 'cancelled')
            ->exists();
        if ($exists) {
            $this->panelAddError = 'Selected court and time slot is already booked.';

            return;
        }
        // Check for cross-court conflicts if enabled
        try {
            $siteSettings = app(\App\Settings\SiteSettings::class);
            if ($siteSettings->isCrossCourtConflictDetectionEnabled()) {
                $crossCourtConflicts = BookingModel::getCrossCourtConflicts(
                    $this->panelAddForm['tenant_id'],
                    $this->panelAddForm['date'],
                    $this->panelAddForm['start_time'],
                    $this->panelAddForm['end_time'],
                    $this->panelAddForm['court_id']
                );
                if (! empty($crossCourtConflicts)) {
                    $conflictDetails = collect($crossCourtConflicts)->map(function ($conflict) {
                        return "{$conflict['court_name']} at {$conflict['start_time']}-{$conflict['end_time']} (Ref: #{$conflict['booking_reference']})";
                    })->implode(', ');
                    $this->panelAddError = "Cross-court conflict detected. Tenant already has bookings: {$conflictDetails}";

                    return;
                }
            }
        } catch (\Exception $e) {
            // If settings are not available, continue without cross-court conflict detection
        }
        if ($this->panelAddForm['date'] === now()->format('Y-m-d') && $this->panelAddForm['start_time'] <= now()->format('H:i')) {
            $this->panelAddError = 'Cannot book a past time slot.';

            return;
        }
        // Determine booking type
        $bookingType = $this->getDateBookingType($bookingDate);
        if ($bookingType === 'none') {
            $this->panelAddError = 'Booking is not allowed for this date.';

            return;
        }
        if ($bookingType === 'premium' && ! $this->isPremiumBookingOpen()) {
            $this->panelAddError = 'Premium booking is not open yet.';

            return;
        }
        $quotaCheck = $tenant->canMakeSpecificTypeBooking($this->panelAddForm['date'], $bookingType, 1);
        if (! $quotaCheck['can_book']) {
            $this->panelAddError = $quotaCheck['reason'] ?? 'Tenant quota exceeded.';

            return;
        }
        $existingBookingsForDate = BookingModel::where('tenant_id', $tenant->id)
            ->where('date', $this->panelAddForm['date'])
            ->where('status', '!=', 'cancelled')
            ->count();
        if ($existingBookingsForDate >= 2) {
            $this->panelAddError = 'Maximum 2 hours per day allowed for this tenant.';

            return;
        }
        $weekStart = $bookingDate->copy()->startOfWeek()->format('Y-m-d');
        $distinctDays = BookingModel::where('tenant_id', $tenant->id)
            ->where('booking_week_start', $weekStart)
            ->where('status', '!=', 'cancelled')
            ->distinct('date')
            ->count('date');
        if ($distinctDays >= 3 && ! BookingModel::where('tenant_id', $tenant->id)->where('date', $this->panelAddForm['date'])->exists()) {
            $this->panelAddError = 'Tenant cannot book for more than 3 distinct days in a week.';

            return;
        }
        $booking = BookingModel::create([
            'tenant_id' => $this->panelAddForm['tenant_id'],
            'court_id' => $this->panelAddForm['court_id'],
            'date' => $this->panelAddForm['date'],
            'start_time' => $this->panelAddForm['start_time'],
            'end_time' => $this->panelAddForm['end_time'],
            'status' => 'confirmed',
            'booking_type' => $bookingType,
            'booking_week_start' => $bookingDate->copy()->startOfWeek()->format('Y-m-d'),
            'notes' => $this->panelAddForm['notes'],
            'approved_by' => auth('admin')->id(),
            'approved_at' => now(),
        ]);
        $booking->calculatePrice();
        $booking->booking_reference = $booking->generateReference();
        $booking->save();
        $this->isAddMode = false;
        $this->showDetailPanel = false;

        // Refresh booking data with new booking
        $this->refreshBookingData($booking);

        session()->flash('message', 'Booking created successfully! Reference: #'.$booking->booking_reference);
    }

    public function handleBookingCardClick($bookingId)
    {
        if ($this->isAddMode) {
            $this->isAddMode = false;
        }
        $this->showDetail($bookingId);
    }

    /**
     * Bulk update booking statuses
     */
    public function bulkUpdateStatus($bookingIds, $status)
    {
        $this->validate([
            'status' => 'required|in:pending,confirmed,cancelled',
        ]);

        $bookings = BookingModel::whereIn('id', $bookingIds)->get();

        foreach ($bookings as $booking) {
            $booking->update([
                'status' => $status,
                'edited_by' => auth('admin')->id(),
                'edited_at' => now(),
            ]);
        }

        // Refresh booking data
        $this->refreshBookingData();

        session()->flash('message', count($bookings) . ' bookings updated successfully.');
    }

    /**
     * Force refresh all booking data
     */
    public function forceRefresh()
    {
        $this->refreshBookingData();
        session()->flash('message', 'Data refreshed successfully.');
    }

    /**
     * Handle real-time booking updates
     */
    public function handleBookingUpdate($bookingId)
    {
        $booking = BookingModel::with(['tenant', 'court', 'approver', 'canceller', 'editor'])->find($bookingId);

        if ($booking) {
            // Update selected booking if it's the one being viewed
            if ($this->selectedBooking && $this->selectedBooking->id === $bookingId) {
                $this->selectedBooking = $booking;
            }

            // Update editing booking if it's the one being edited
            if ($this->editingBooking && $this->editingBooking->id === $bookingId) {
                $this->editingBooking = $booking;
            }

            // Refresh all data
            $this->refreshBookingData();
        }
    }

    /**
     * Handle booking deletion
     */
    public function handleBookingDeletion($bookingId)
    {
        // Close detail panel if the deleted booking was being viewed
        if ($this->selectedBooking && $this->selectedBooking->id === $bookingId) {
            $this->closeDetailPanel();
        }

        // Close edit modal if the deleted booking was being edited
        if ($this->editingBooking && $this->editingBooking->id === $bookingId) {
            $this->closeEditModal();
        }

        // Refresh all data
        $this->refreshBookingData();

        session()->flash('message', 'Booking deleted successfully.');
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
    public function canBookPremium($date): bool
    {
        $nextWeekEnd = now()->addWeek()->endOfWeek();
        $premiumEnd = now()->addMonth()->endOfMonth();

        // Check if premium booking is currently open
        $isPremiumBookingOpen = $this->isPremiumBookingOpen();

        return $date->gt($nextWeekEnd) && $date->lte($premiumEnd) && $isPremiumBookingOpen;
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
     * Check if a slot can be booked (not past date/time)
     *
     * @param  string  $date  - Date in Y-m-d format
     * @param  string  $startTime  - Start time in H:i format
     * @return bool - True if slot can be booked
     */
    public function canBookSlot($date, $startTime)
    {
        $bookingDate = \Carbon\Carbon::parse($date);
        $bookingTime = \Carbon\Carbon::createFromFormat('H:i', $startTime);

        // Combine date and time
        $bookingDateTime = $bookingDate->copy()->setTime($bookingTime->hour, $bookingTime->minute);

        // Check if it's in the past
        return $bookingDateTime->isFuture();
    }

    /**
     * Check if premium booking is currently open
     */
    private function isPremiumBookingOpen(): bool
    {
        // Set premium booking date using override if available, fallback to 25th
        $currentDate = now();
        $premiumBookingDate = \App\Models\PremiumDateOverride::getCurrentMonthPremiumDate();

        if ($currentDate->toDateString() > $premiumBookingDate->toDateString()) {
            $nextMonthPremiumDate = \App\Models\PremiumDateOverride::whereMonth('date', $currentDate->copy()->addMonth()->month)
                ->whereYear('date', $currentDate->copy()->addMonth()->year)
                ->first();

            $premiumBookingDate = $nextMonthPremiumDate ? \Carbon\Carbon::parse($nextMonthPremiumDate->date) : $currentDate->copy()->addMonth()->day(25);
        }

        return now()->format('Y-m-d') === $premiumBookingDate->format('Y-m-d');
    }

    // Export methods
    public function openExportModal()
    {
        $this->exportDateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->exportDateTo = now()->endOfMonth()->format('Y-m-d');
        $this->exportStatusFilter = '';
        $this->exportCourtFilter = '';
        $this->exportBookingTypeFilter = '';
        $this->showExportModal = true;
    }

    public function closeExportModal()
    {
        $this->showExportModal = false;
        $this->isExporting = false;
    }

    public function exportBookings()
    {
        $this->validate([
            'exportDateFrom' => 'required|date',
            'exportDateTo' => 'required|date|after_or_equal:exportDateFrom',
            'exportFormat' => 'required|in:excel,pdf',
        ]);

        $this->isExporting = true;

        try {
            // Build query for export
            $query = BookingModel::with(['tenant', 'court', 'approver', 'canceller'])
                ->whereBetween('date', [$this->exportDateFrom, $this->exportDateTo])
                ->orderBy('date', 'desc')
                ->orderBy('start_time', 'asc');

            // Apply filters
            if ($this->exportStatusFilter) {
                $query->where('status', BookingStatusEnum::from($this->exportStatusFilter));
            }

            if ($this->exportCourtFilter) {
                $query->where('court_id', $this->exportCourtFilter);
            }

            if ($this->exportBookingTypeFilter) {
                $query->where('booking_type', $this->exportBookingTypeFilter);
            }

            $bookings = $query->get();

            // Prepare filters for export
            $filters = [
                'date_from' => $this->exportDateFrom,
                'date_to' => $this->exportDateTo,
                'status' => $this->exportStatusFilter,
                'court' => $this->exportCourtFilter,
                'booking_type' => $this->exportBookingTypeFilter,
            ];

            if ($this->exportFormat === 'excel') {
                return $this->exportToExcel($bookings, $filters);
            } else {
                return $this->exportToPdf($bookings, $filters);
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Export failed: '.$e->getMessage());
            Log::error('Export failed: '.$e->getMessage());
        } finally {
            $this->isExporting = false;
        }
    }

    private function exportToExcel($bookings, $filters)
    {
        $filename = 'bookings_report_'.now()->format('Y-m-d_H-i-s').'.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BookingsExport($bookings, $filters),
            $filename
        );
    }

    private function exportToPdf($bookings, $filters)
    {
        $pdfExport = new \App\Exports\BookingsPdfExport($bookings, $filters);
        $html = $pdfExport->generateHtml();

        $filename = 'bookings_report_'.now()->format('Y-m-d_H-i-s').'.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function render()
    {
        return view('livewire.admin.booking.main');
    }
}
