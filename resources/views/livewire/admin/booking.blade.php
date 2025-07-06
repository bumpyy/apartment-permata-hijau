<?php

namespace App\Http\Livewire\Admin;

use App\Enum\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('components.backend.layouts.app')]
class extends Component
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
            $this->resetPage();
            $this->clearCache();
        }
    }

    public function showDetail($bookingId)
    {
        // Use cached booking if available, otherwise fetch with eager loading
        if ($this->cachedBookings) {
            $this->selectedBooking = $this->cachedBookings->firstWhere('id', $bookingId);
        } else {
            $this->selectedBooking = Booking::with([
                'tenant',
                'court',
                'approver',
                'canceller',
                'editor',
            ])->find($bookingId);
        }
        $this->showDetailPanel = true;
    }

    public function closeDetailPanel()
    {
        $this->showDetailPanel = false;
    }

    public function edit($bookingId)
    {
        $booking = $this->cachedBookings ?
            $this->cachedBookings->firstWhere('id', $bookingId) :
            Booking::find($bookingId);

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
            $this->clearCache();

            session()->flash('message', 'Booking updated successfully.');
            $this->dispatch('close-edit-modal');
        }
    }

    public function confirmBooking($bookingId)
    {
        $booking = Booking::find($bookingId);
        if ($booking) {
            $booking->update([
                'status' => 'confirmed',
                'approved_by' => auth('admin')->id(),
                'approved_at' => now(),
            ]);
            $this->selectedBooking = $booking->fresh(['tenant', 'court']);
            $this->clearCache();
            session()->flash('message', 'Booking confirmed successfully.');
        }
    }

    public function openCancelModal($bookingId)
    {
        $booking = Booking::find($bookingId);
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
        if (!$this->bookingToCancel) {
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

        $this->selectedBooking = $this->bookingToCancel->fresh(['tenant', 'court']);
        $this->clearCache();
        session()->flash('message', 'Booking cancelled successfully.');
        $this->closeCancelModal();
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingBooking = null;
        $this->reset('editForm');
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
        return Booking::with(['tenant', 'court'])
            ->whereDate('date', Carbon::today())
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->orderBy('start_time')
            ->get()
            ->groupBy('court.name');
    }

    public function getUpcomingBookingsProperty()
    {
        return Booking::with(['tenant', 'court'])
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
        return Booking::where('date', '<', now()->startOfDay())->count();
    }

    public function getActiveBookingsCountProperty()
    {
        return Booking::where('date', '>=', now()->startOfDay())->count();
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
        $this->clearCache();
    }

    public function nextWeek()
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->startOfWeek()->format('Y-m-d');
        $this->clearCache();
    }

    public function goToToday()
    {
        $this->weekStart = now()->startOfWeek()->format('Y-m-d');
        $this->weekPicker = $this->weekStart;
        $this->clearCache();
    }

    public function updatedWeekPicker()
    {
        if ($this->weekPicker) {
            $this->weekStart = Carbon::parse($this->weekPicker)->startOfWeek()->format('Y-m-d');
            $this->clearCache();
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
        $this->clearCache();
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;
    }

    public function setTableTab($tab)
    {
        $this->tableTab = $tab;
        $this->resetPage();
        $this->clearCache();
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
        $query = Booking::with(['tenant', 'court'])
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
        $this->cachedStats = Booking::selectRaw('
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

        $bookings = \App\Models\Booking::where('court_id', $this->addForm['court_id'])
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

        // Additional validation for past dates and times
        $bookingDate = \Carbon\Carbon::parse($this->addForm['date']);
        if ($bookingDate->isPast()) {
            $this->addError = 'Cannot book on a past date.';
            return;
        }
        [$start, $end] = explode('-', $this->addForm['time_slot']);
        // Check again for slot conflict
        $exists = \App\Models\Booking::where('court_id', $this->addForm['court_id'])
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
                $crossCourtConflicts = \App\Models\Booking::getCrossCourtConflicts(
                    $this->addForm['tenant_id'],
                    $this->addForm['date'],
                    $start,
                    $end,
                    $this->addForm['court_id']
                );

                if (!empty($crossCourtConflicts)) {
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
        $bookingDate = \Carbon\Carbon::parse($this->addForm['date']);
        $bookingType = $this->getDateBookingType($bookingDate);

        $booking = \App\Models\Booking::create([
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
        $this->clearCache();
        session()->flash('message', 'Booking created successfully! Reference: #'.$booking->booking_reference);
    }

    public function startAddBooking($date, $startTime, $endTime)
    {
        // Prevent booking for past dates/times
        if (!$this->canBookSlot($date, $startTime)) {
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
            $isBooked = \App\Models\Booking::where('court_id', $court->id)
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

        // Additional validation for past dates and times
        $bookingDate = \Carbon\Carbon::parse($this->panelAddForm['date']);
        if ($bookingDate->isPast()) {
            $this->panelAddError = 'Cannot book on a past date.';
            return;
        }

        if ($bookingDate->isToday() && $this->panelAddForm['start_time'] <= now()->format('H:i')) {
            $this->panelAddError = 'Cannot book a past time slot for today.';
            return;
        }
        // Check again for slot conflict
        $exists = \App\Models\Booking::where('court_id', $this->panelAddForm['court_id'])
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
                $crossCourtConflicts = \App\Models\Booking::getCrossCourtConflicts(
                    $this->panelAddForm['tenant_id'],
                    $this->panelAddForm['date'],
                    $this->panelAddForm['start_time'],
                    $this->panelAddForm['end_time'],
                    $this->panelAddForm['court_id']
                );

                if (!empty($crossCourtConflicts)) {
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
        // Prevent booking in the past
        if ($this->panelAddForm['date'] === now()->format('Y-m-d') && $this->panelAddForm['start_time'] <= now()->format('H:i')) {
            $this->panelAddError = 'Cannot book a past time slot.';

            return;
        }

        // Determine booking type
        $bookingDate = \Carbon\Carbon::parse($this->panelAddForm['date']);
        $bookingType = $this->getDateBookingType($bookingDate);

        $booking = \App\Models\Booking::create([
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
        $this->clearCache();
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
     * @param string $date - Date in Y-m-d format
     * @param string $startTime - Start time in H:i format
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
            $query = Booking::with(['tenant', 'court', 'approver', 'canceller'])
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

            if ($bookings->isEmpty()) {
                session()->flash('error', 'No bookings found for the selected criteria.');
                $this->isExporting = false;
                return;
            }

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
            session()->flash('error', 'Export failed: ' . $e->getMessage());
            \Log::error('Export failed: ' . $e->getMessage());
        } finally {
            $this->isExporting = false;
        }
    }

    private function exportToExcel($bookings, $filters)
    {
        $filename = 'bookings_report_' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\BookingsExport($bookings, $filters),
            $filename
        );
    }

    private function exportToPdf($bookings, $filters)
    {
        $pdfExport = new \App\Exports\BookingsPdfExport($bookings, $filters);
        $html = $pdfExport->generateHtml();

        $filename = 'bookings_report_' . now()->format('Y-m-d_H-i-s') . '.pdf';

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
        $pdf->setPaper('A4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }
}

?>

<div class="mx-auto max-w-7xl p-8">
    <!-- Page Header with Quick Actions -->
    <div class="mb-8">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Booking Management</h1>
                <p class="mt-1 text-sm text-gray-500">Manage and monitor all tennis court bookings</p>
            </div>
            <div class="mt-4 sm:mt-0 flex flex-col sm:flex-row gap-3">
                <button wire:click="openAddModal" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Booking
                </button>
                <button wire:click="openExportModal" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export Report
                </button>
            </div>
        </div>
    </div>

    <!-- Left Column: Dashboard, Filters, Bookings Table -->
    <div>
        <!-- Dashboard Stats -->
        <div class="mb-6 grid grid-cols-4 gap-4">
            <div class="rounded-xl bg-white shadow p-4 text-center">
                <div class="text-2xl font-bold text-blue-700">{{ $this->totalBookings }}</div>
                <div class="text-xs text-gray-500 mt-1">Total Bookings</div>
            </div>
            <div class="rounded-xl bg-white shadow p-4 text-center">
                <div class="text-2xl font-bold text-green-700">{{ $this->activeBookings }}</div>
                <div class="text-xs text-gray-500 mt-1">Confirmed</div>
            </div>
            <div class="rounded-xl bg-white shadow p-4 text-center">
                <div class="text-2xl font-bold text-yellow-600">{{ $this->pendingBookings }}</div>
                <div class="text-xs text-gray-500 mt-1">Pending</div>
            </div>
            <div class="rounded-xl bg-white shadow p-4 text-center">
                <div class="text-2xl font-bold text-red-600">{{ $this->cancelledBookings }}</div>
                <div class="text-xs text-gray-500 mt-1">Cancelled</div>
            </div>
        </div>

        <!-- Today's Bookings Section -->
        @if($this->todaysBookings->isNotEmpty())
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                    Today's Bookings ({{ Carbon::today()->format('l, d M Y') }})
                </h2>
                <button wire:click="$set('showTodaysBookings', !$showTodaysBookings)" class="text-sm text-blue-600 hover:text-blue-800">
                    {{ $showTodaysBookings ? 'Hide' : 'Show' }}
                </button>
            </div>

            @if($showTodaysBookings)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($this->todaysBookings as $courtName => $bookings)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-4 py-3">
                        <h3 class="text-white font-semibold text-lg">Court {{ $courtName }}</h3>
                        <p class="text-blue-100 text-sm">{{ count($bookings) }} booking(s) today</p>
                    </div>
                    <div class="p-4 space-y-3">
                        @foreach($bookings as $booking)
                        <div class="bg-gray-50 rounded-lg p-3 border-l-4 border-blue-500">
                            <div class="flex items-center justify-between mb-2">
                                <div class="font-semibold text-gray-900">{{ $booking->tenant->name }}</div>
                                <span @class([
                                    "inline-flex px-2 py-1 text-xs font-semibold rounded-full",
                                    "bg-green-100 text-green-800" => $booking->status === BookingStatusEnum::CONFIRMED,
                                    "bg-yellow-100 text-yellow-800" => $booking->status === BookingStatusEnum::PENDING,
                                ])>
                                    {{ ucfirst($booking->status->value) }}
                                </span>
                            </div>
                            <div class="text-sm text-gray-600">
                                <div class="flex items-center mb-1">
                                    <svg class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ $booking->start_time->format('g:i A') }} - {{ $booking->end_time->format('g:i A') }}
                                </div>
                                @if($booking->is_light_required)
                                <div class="flex items-center text-orange-600 text-xs">
                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.002z"></path>
                                    </svg>
                                    Lights required
                                </div>
                                @endif
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-gray-500">#{{ $booking->booking_reference }}</span>
                                <button wire:click="showDetail({{ $booking->id }})" class="text-xs text-blue-600 hover:text-blue-800">
                                    View Details
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @endif

        <!-- Upcoming Bookings Preview -->
        @if($this->upcomingBookings->isNotEmpty())
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Upcoming Bookings (Next 10)
                </h2>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Court</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($this->upcomingBookings as $date => $bookings)
                                <tr class="bg-gray-50">
                                    <td colspan="5" class="px-4 py-2 font-semibold text-sm text-gray-700">
                                        {{ Carbon::parse($date)->format('l, d M Y') }}
                                    </td>
                                </tr>
                                @foreach($bookings as $booking)
                                <tr wire:click="showDetail({{ $booking->id }})" class="hover:bg-blue-50 cursor-pointer">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $booking->date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $booking->court->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $booking->tenant->name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $booking->start_time->format('H:i') }} - {{ $booking->end_time->format('H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <span @class([
                                            "inline-flex px-2 py-1 text-xs font-semibold rounded-full",
                                            "bg-green-100 text-green-800" => $booking->status === BookingStatusEnum::CONFIRMED,
                                            "bg-yellow-100 text-yellow-800" => $booking->status === BookingStatusEnum::PENDING,
                                        ])>
                                            {{ ucfirst($booking->status->value) }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- New Booking Button -->
        {{-- <div class="mb-6 flex justify-end">
            <a href="{{ route('admin.booking.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                New Booking
            </a>
            <button wire:click="openAddModal" class="ml-4 inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add Booking
            </button>
        </div> --}}

        <!-- Court Filter Tabs -->
        <div class="mb-4 flex gap-2 overflow-x-auto pb-2">
            <button wire:click="filterByCourt('')" class="px-4 py-2 rounded font-medium focus:outline-none whitespace-nowrap {{ $courtFilter === '' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">All</button>
            @foreach($this->courts as $court)
                <button wire:click="filterByCourt('{{ $court->id }}')" class="px-4 py-2 rounded font-medium focus:outline-none whitespace-nowrap {{ $courtFilter == $court->id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $court->name }}</button>
            @endforeach
        </div>

        <!-- View Toggle and Export -->
        <div class="mb-4 flex justify-between items-center">
            <div class="flex gap-2">
                <button wire:click="setViewMode('table')" class="px-4 py-2 rounded font-medium focus:outline-none {{ $viewMode === 'table' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">Table View</button>
                <button wire:click="setViewMode('weekly')" class="px-4 py-2 rounded font-medium focus:outline-none {{ $viewMode === 'weekly' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-700' }}">Weekly View</button>
            </div>

            <!-- Export Button -->
            <button wire:click="openExportModal" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:bg-purple-700 active:bg-purple-900 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Export Report
            </button>
        </div>

        <div class="flex flex-col lg:flex-row relative gap-6">
            <div class="flex-1 min-w-0">
                @if($viewMode === 'table')
                    <!-- Filter Bar -->
                    <div class="mb-6 bg-white shadow rounded-xl p-4 flex flex-col md:flex-row md:items-center gap-4">
                        <div class="flex-1 flex items-center gap-2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m0 0A7.5 7.5 0 104.5 4.5a7.5 7.5 0 0012.15 12.15z"/></svg>
                            <input wire:model.live.debounce.500ms="search" type="search" placeholder="Search bookings..." class="w-full rounded border border-gray-200 p-2 focus:ring-2 focus:ring-blue-200" />
                        </div>
                        <select wire:model.live="statusFilter" class="rounded border border-gray-200 p-2 focus:ring-2 focus:ring-blue-200">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <input type="date" wire:model.live="dateFilter" class="rounded border border-gray-200 p-2 focus:ring-2 focus:ring-blue-200" />
                        <div class="flex items-center gap-2">
                            <input type="checkbox" wire:model.live="excludeCancelled" id="excludeCancelled" class="rounded border-gray-300" />
                            <label for="excludeCancelled" class="text-sm text-gray-700 whitespace-nowrap">Exclude Cancelled</label>
                        </div>
                    </div>

                    <!-- Table Tabs -->
                    <div class="mb-4 flex gap-2">
                        <button wire:click="setTableTab('active')" class="px-4 py-2 rounded font-medium focus:outline-none transition-colors {{ $tableTab === 'active' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            📅 Active Bookings ({{ $this->activeBookingsCount }})
                        </button>
                        <button wire:click="setTableTab('past')" class="px-4 py-2 rounded font-medium focus:outline-none transition-colors {{ $tableTab === 'past' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            📚 Past Bookings ({{ $this->pastBookingsCount }})
                        </button>
                    </div>

                    <!-- Table Header -->
                    <div class="mb-4 bg-white shadow rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                @if($tableTab === 'active')
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full bg-green-500"></div>
                                        <h3 class="text-lg font-semibold text-gray-800">Active Bookings</h3>
                                        <span class="text-sm text-gray-500">(Future and today's bookings)</span>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2">
                                        <div class="w-3 h-3 rounded-full bg-orange-500"></div>
                                        <h3 class="text-lg font-semibold text-gray-800">Past Bookings</h3>
                                        <span class="text-sm text-gray-500">(Historical booking records)</span>
                                    </div>
                                @endif
                            </div>
                            <div class="text-sm text-gray-500">
                                Showing {{ $this->bookings->count() }} of {{ $this->bookings->total() }} bookings
                            </div>
                        </div>
                    </div>

                    <!-- Bookings Table -->
                    <div class="rounded-xl bg-white shadow overflow-x-auto cursor-grab active:cursor-grabbing"
                         x-data="{
                             isDown: false,
                             startX: 0,
                             scrollLeft: 0
                         }"
                         x-on:mousedown="isDown = true; startX = $event.pageX - $el.offsetLeft; scrollLeft = $el.scrollLeft; $el.classList.add('cursor-grabbing')"
                         x-on:mouseleave="isDown = false; $el.classList.remove('cursor-grabbing')"
                         x-on:mouseup="isDown = false; $el.classList.remove('cursor-grabbing')"
                         x-on:mousemove="$event.preventDefault(); if (isDown) { const x = $event.pageX - $el.offsetLeft; const walk = (x - startX) * 2; $el.scrollLeft = scrollLeft - walk; }"
                         x-on:selectstart="$event.preventDefault()">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tenant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Court</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($this->groupedBookings as $date => $courts)
                                    <tr>
                                        <td colspan="7" class="bg-gray-100 font-bold text-base px-6 py-2 border-t border-b border-gray-200">{{ \Carbon\Carbon::parse($date)->format('l, d M Y') }}</td>
                                    </tr>
                                    @foreach($courts as $courtName => $bookings)
                                        <tr>
                                            <td colspan="7" class="bg-blue-50 font-semibold text-sm px-6 py-2 border-t border-b border-blue-200">Court: {{ $courtName }}</td>
                                        </tr>
                                        @foreach($bookings as $booking)
                                            @php
                                                $isPastBooking = $booking->date->isPast();
                                            @endphp
                                            <tr wire:click="handleBookingCardClick({{ $booking->id }})" @class([
                                                'hover:bg-blue-50 cursor-pointer',
                                                'bg-gray-50' => $isPastBooking && $tableTab === 'past',
                                                'opacity-75' => $isPastBooking && $tableTab === 'past',
                                            ])>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="font-semibold text-gray-900">{{ $booking->tenant->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $booking->tenant->email }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->booking_reference }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->court->name ?? '-' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <div class="flex items-center gap-2">
                                                        <span>{{ $booking->date->format('Y-m-d') }}</span>
                                                        @if($isPastBooking && $tableTab === 'past')
                                                            <span class="text-xs text-orange-600 bg-orange-100 px-2 py-1 rounded-full">Past</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->start_time instanceof \Carbon\Carbon ? $booking->start_time->format('H:i') : $booking->start_time }} - {{ $booking->end_time instanceof \Carbon\Carbon ? $booking->end_time->format('H:i') : $booking->end_time }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @php
                                                        $statusColors = [
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'confirmed' => 'bg-green-100 text-green-800',
                                                            'cancelled' => 'bg-red-100 text-red-800'
                                                        ];
                                                        $colorClass = $statusColors[$booking->status->value] ?? 'bg-gray-100 text-gray-800';
                                                    @endphp
                                                    <span @class(["inline-flex px-2 py-1 text-xs font-semibold rounded-full", $colorClass])>
                                                        {{ ucfirst($booking->status->value) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>

                    </div>

                    <div class="p-4">
                         {{ $this->bookings->links() }}
                    </div>
                @else
                    <!-- Weekly Kanban View -->

                    <!-- Weekly Filter -->
                    <div class="mb-6 bg-white shadow rounded-xl p-4">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <button wire:click="prevWeek" class="px-3 py-2 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                    </svg>
                                </button>
                                <div class="font-semibold text-gray-700 text-center min-w-[200px]">
                                    {{ $this->weeklyBookings['startOfWeek']->format('d M Y') }} - {{ $this->weeklyBookings['endOfWeek']->format('d M Y') }}
                                </div>
                                <button wire:click="nextWeek" class="px-3 py-2 rounded bg-gray-100 hover:bg-gray-200 text-gray-700 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model.live="excludeCancelled" id="excludeCancelledWeekly" class="rounded border-gray-300" />
                                    <label for="excludeCancelledWeekly" class="text-sm text-gray-700 whitespace-nowrap">Exclude Cancelled</label>
                                </div>
                                <input type="date" wire:model.live="weekPicker" class="rounded border border-gray-200 p-2 focus:ring-2 focus:ring-blue-200 text-sm">
                                <button wire:click="goToToday" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 transition-colors text-sm font-medium">
                                    Today
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Weekly List -->
                    <div class="rounded-xl bg-white shadow p-4 overflow-x-auto cursor-grab active:cursor-grabbing"
                         x-data="{
                             isDown: false,
                             startX: 0,
                             scrollLeft: 0
                         }"
                         x-on:mousedown="isDown = true; startX = $event.pageX - $el.offsetLeft; scrollLeft = $el.scrollLeft; $el.classList.add('cursor-grabbing')"
                         x-on:mouseleave="isDown = false; $el.classList.remove('cursor-grabbing')"
                         x-on:mouseup="isDown = false; $el.classList.remove('cursor-grabbing')"
                         x-on:mousemove="$event.preventDefault(); if (isDown) { const x = $event.pageX - $el.offsetLeft; const walk = (x - startX) * 2; $el.scrollLeft = scrollLeft - walk; }"
                         x-on:selectstart="$event.preventDefault()">
                        <div class="flex gap-4 min-w-[900px]">
                            @foreach($this->weeklyBookings['days'] as $date => $slots)
                                @php
                                    $dateObj = \Carbon\Carbon::parse($date);
                                    $isPast = $dateObj->isPast();
                                    $isToday = $dateObj->isToday();
                                @endphp
                                <div class="flex-1 min-w-[180px]">
                                    <div @class([
                                        'text-xs font-bold mb-2 text-center border-b pb-1',
                                        'text-gray-500' => !$isPast,
                                        'text-gray-400' => $isPast,
                                    ])>
                                        {{ $dateObj->format('D, d M') }}
                                        @if($isPast)
                                            <span class="text-red-500 ml-1">🔒</span>
                                        @elseif($isToday)
                                            <span class="text-blue-500 ml-1">📅</span>
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-2 min-h-[120px]">
                                        @foreach($this->weeklyBookings['timeSlots'] as $slotLabel)
                                            <div class="mb-2">
                                                <div class="text-[11px] text-gray-400 font-semibold mb-1">{{ $slotLabel['label'] }}</div>
                                                @forelse($slots[$slotLabel['label']] as $booking)
                                                    <div
                                                        wire:click="handleBookingCardClick({{ $booking->id }})"
                                                        @class([
                                                            'rounded-lg border p-2 shadow-sm mb-1 transition-colors',
                                                            'bg-gray-50 hover:bg-gray-100 cursor-pointer' => true,
                                                        ])
                                                    >
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <span class="inline-block bg-blue-100 text-blue-800 text-[10px] font-bold rounded px-2 py-0.5">{{ $booking->court->name ?? '-' }}</span>
                                                            <div class="font-semibold text-gray-800 text-xs">{{ $booking->tenant->name }}</div>
                                                            <span @class([
                                                                'inline-block text-[10px] font-bold rounded px-2 py-0.5',
                                                                'bg-green-100 text-green-800' => $booking->booking_type === 'free',
                                                                'bg-purple-100 text-purple-800' => $booking->booking_type === 'premium',
                                                            ])>
                                                                @if($booking->booking_type === 'free')
                                                                    🆓
                                                                @else
                                                                    ⭐
                                                                @endif
                                                            </span>
                                                        </div>
                                                        <div class="text-[11px] text-gray-500 mb-0.5">{{ $booking->start_time instanceof \Carbon\Carbon ? $booking->start_time->format('H:i') : $booking->start_time }} - {{ $booking->end_time instanceof \Carbon\Carbon ? $booking->end_time->format('H:i') : $booking->end_time }}</div>
                                                        <div class="mt-1">
                                                            @php
                                                                $statusColors = [
                                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                                    'confirmed' => 'bg-green-100 text-green-800',
                                                                    'cancelled' => 'bg-red-100 text-red-800'
                                                                ];
                                                                $colorClass = $statusColors[$booking->status->value] ?? 'bg-gray-100 text-gray-800';
                                                            @endphp
                                                            <span @class(["inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold", $colorClass])>
                                                                {{ ucfirst($booking->status->value) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                @empty
                                                    @php
                                                        $canBook = $this->canBookSlot($date, $slotLabel['start_time']);
                                                        $isPast = !$canBook;
                                                        $isToday = \Carbon\Carbon::parse($date)->isToday();
                                                        $isPastTime = $isToday && \Carbon\Carbon::createFromFormat('H:i', $slotLabel['start_time'])->isPast();
                                                    @endphp
                                                    <div
                                                        @if($canBook)
                                                            wire:click="startAddBooking('{{ $date }}', '{{ $slotLabel['start_time'] }}', '{{ $slotLabel['end_time'] }}')"
                                                        @endif
                                                        @class([
                                                            'rounded-lg border p-2 text-xs text-center mb-1 transition',
                                                            'border-dashed bg-gray-50 text-blue-500 cursor-pointer hover:bg-blue-50' => $canBook,
                                                            'border-gray-300 bg-gray-100 text-gray-400 cursor-not-allowed' => $isPast,
                                                        ])
                                                        title="{{ $canBook ? 'Add booking for this slot' : ($isPastTime ? 'Past time slot - cannot book' : 'Past date - cannot book') }}"
                                                    >
                                                        @if($canBook)
                                                            + Add Booking
                                                        @elseif($isPastTime)
                                                            ⏰ Past Time
                                                        @else
                                                            🔒 Past Date
                                                        @endif
                                                    </div>
                                                @endforelse
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Legend for Weekly View -->
                    <div class="mt-4 bg-white shadow rounded-xl p-4">
                        <h4 class="text-sm font-semibold text-gray-700 mb-2">📋 Booking Status Legend</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-green-100 border border-green-300 rounded"></div>
                                <span>🆓 Free Booking</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-purple-100 border border-purple-300 rounded"></div>
                                <span>⭐ Premium Booking</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-gray-100 border border-gray-300 rounded"></div>
                                <span>🔒 Past Date/Time</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 bg-blue-100 border border-blue-300 rounded"></div>
                                <span>📅 Today</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if($showDetailPanel)
                <div class="w-full lg:w-[350px] xl:w-[400px] flex-shrink-0">
                    <div class="bg-white rounded-xl shadow-xl border border-gray-100 p-8 min-h-[400px] flex flex-col justify-between sticky top-4"
                         x-data="{ showConfirm: false, showCancel: false, showEdit: false }"
                         @close-edit-modal.window="showEdit = false">
                        <button wire:click="closeDetailPanel" class="absolute top-3 right-3 text-gray-400 hover:text-gray-700 text-xl" title="Close">&times;</button>
                        <div>
                            @if($isAddMode)
                                <form wire:submit.prevent="createBookingFromPanel" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium">Court</label>
                                        <select wire:model="panelAddForm.court_id" class="w-full rounded border p-2" required>
                                            <option value="">Select Court</option>
                                            @foreach($panelAvailableCourts as $court)
                                                <option value="{{ $court['id'] }}" @if($court['is_booked']) disabled @endif>
                                                    {{ $court['name'] }} @if($court['is_booked']) (Booked) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">Tenant</label>
                                        <select wire:model="panelAddForm.tenant_id" class="w-full rounded border p-2" required>
                                            <option value="">Select Tenant</option>
                                            @foreach($panelTenants as $tenant)
                                                <option value="{{ $tenant->id }}">{{ $tenant->display_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">Date</label>
                                        <input type="text" class="w-full rounded border p-2 bg-gray-100" value="{{ $panelAddForm['date'] }}" readonly>
                                        @php
                                            $selectedDate = \Carbon\Carbon::parse($panelAddForm['date']);
                                            $bookingType = $this->getDateBookingType($selectedDate);
                                            $isToday = $selectedDate->isToday();
                                            $isPast = $selectedDate->isPast();
                                        @endphp
                                        <div class="mt-1 text-xs">
                                            @if($isPast)
                                                <span class="text-red-600">⚠️ Past date - booking not allowed</span>
                                            @elseif($isToday)
                                                <span class="text-orange-600">⚠️ Today - only future time slots available</span>
                                            @elseif($bookingType === 'free')
                                                <span class="text-green-600">🆓 Free booking</span>
                                            @elseif($bookingType === 'premium')
                                                <span class="text-purple-600">⭐ Premium booking</span>
                                            @else
                                                <span class="text-gray-600">🔒 No booking available for this date</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">Time</label>
                                        <input type="text" class="w-full rounded border p-2 bg-gray-100" value="{{ $panelAddForm['start_time'] }} - {{ $panelAddForm['end_time'] }}" readonly>
                                        @php
                                            $isPeak = \Carbon\Carbon::createFromFormat('H:i', $panelAddForm['start_time'])->hour >= 18;
                                        @endphp
                                        @if($isPeak)
                                            <div class="mt-1 text-xs text-orange-600">💡 Lights required (peak hours)</div>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium">Notes</label>
                                        <textarea wire:model="panelAddForm.notes" class="w-full rounded border p-2"></textarea>
                                    </div>
                                    @if($panelAddError)
                                        <div class="text-red-600 text-sm mt-2">{{ $panelAddError }}</div>
                                    @endif
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="cancelAddBooking" class="px-4 py-2 rounded border">Cancel</button>
                                        <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white">Create Booking</button>
                                    </div>
                                </form>
                            @elseif($selectedBooking)
                                @php
                                    $bookingDateTime = $selectedBooking->date->copy()->setTime(
                                        $selectedBooking->start_time->hour,
                                        $selectedBooking->start_time->minute
                                    );
                                    $isPastBooking = $bookingDateTime->isPast();
                                @endphp
                                <div class="flex items-center justify-between mb-6">
                                    <div>
                                        <div class="text-xs text-gray-400">Prepared for</div>
                                        <div class="font-bold text-lg text-gray-800">{{ $selectedBooking->tenant->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $selectedBooking->tenant->email }}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs text-gray-400">Date</div>
                                        <div class="flex items-center gap-2">
                                            <div @class([
                                                'font-semibold',
                                                'text-gray-700' => !$isPastBooking,
                                                'text-gray-500' => $isPastBooking,
                                            ])>{{ $selectedBooking->date->format('d F, Y') }}</div>
                                            @if($isPastBooking)
                                                <span class="text-red-500 text-sm">🔒</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-xs text-gray-400 mb-1">Court</div>
                                    <div class="font-semibold text-gray-700">{{ $selectedBooking->court->name ?? '-' }}</div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-xs text-gray-400 mb-1">Time</div>
                                    <div class="font-semibold text-gray-700">{{ $selectedBooking->start_time->format('H:i') }} - {{ $selectedBooking->end_time->format('H:i') }}</div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-xs text-gray-400 mb-1">Status</div>
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'confirmed' => 'bg-green-100 text-green-800',
                                            'cancelled' => 'bg-red-100 text-red-800'
                                        ];
                                        $colorClass = $statusColors[$selectedBooking->status->value] ?? 'bg-gray-100 text-gray-800';
                                    @endphp
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold {{ $colorClass }}">
                                        {{ ucfirst($selectedBooking->status->value) }}
                                    </span>
                                </div>
                                <div class="mb-4">
                                    <div class="text-xs text-gray-400 mb-1">Type</div>
                                    <div class="flex items-center gap-2">
                                        <span @class([
                                            'inline-flex items-center px-2 py-1 text-xs font-medium rounded-full',
                                            'bg-green-100 text-green-800' => $selectedBooking->booking_type === 'free',
                                            'bg-purple-100 text-purple-800' => $selectedBooking->booking_type === 'premium',
                                        ])>
                                            @if($selectedBooking->booking_type === 'free')
                                                🆓 Free
                                            @else
                                                ⭐ Premium
                                            @endif
                                        </span>
                                        <span class="font-semibold text-gray-700">{{ ucfirst($selectedBooking->booking_type) }}</span>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="text-xs text-gray-400 mb-1">Light Required</div>
                                    <div class="font-semibold text-gray-700">{{ $selectedBooking->is_light_required ? 'Yes' : 'No' }}</div>
                                </div>
                                @if($selectedBooking->notes)
                                    <div class="mb-4">
                                        <div class="text-xs text-gray-400 mb-1">Notes</div>
                                        <div class="text-gray-600">{{ $selectedBooking->notes }}</div>
                                    </div>
                                @endif

                                <!-- User Action Information -->
                                @if($selectedBooking->approved_by)
                                    <div class="mb-4">
                                        <div class="text-xs text-gray-400 mb-1">Confirmed By</div>
                                        <div class="font-semibold text-gray-700">{{ $selectedBooking->approver->name ?? 'Unknown User' }}</div>
                                        <div class="text-xs text-gray-500">{{ $selectedBooking->approved_at ? $selectedBooking->approved_at->format('d M Y, H:i') : '' }}</div>
                                    </div>
                                @endif

                                @if($selectedBooking->cancelled_by)
                                    <div class="mb-4">
                                        <div class="text-xs text-gray-400 mb-1">Cancelled By</div>
                                        <div class="font-semibold text-gray-700">{{ $selectedBooking->canceller->name ?? 'Unknown User' }}</div>
                                        <div class="text-xs text-gray-500">{{ $selectedBooking->cancelled_at ? $selectedBooking->cancelled_at->format('d M Y, H:i') : '' }}</div>
                                    </div>
                                @endif

                                @if($selectedBooking->edited_by)
                                    <div class="mb-4">
                                        <div class="text-xs text-gray-400 mb-1">Last Edited By</div>
                                        <div class="font-semibold text-gray-700">{{ $selectedBooking->editor->name ?? 'Unknown User' }}</div>
                                        <div class="text-xs text-gray-500">{{ $selectedBooking->edited_at ? $selectedBooking->edited_at->format('d M Y, H:i') : '' }}</div>
                                    </div>
                                @endif

                                @if($selectedBooking->cancellation_reason)
                                    <div class="mb-4">
                                        <div class="text-xs text-gray-400 mb-1">Cancellation Reason</div>
                                        <div class="text-gray-600 bg-red-50 border border-red-200 rounded-lg p-3">
                                            {{ $selectedBooking->cancellation_reason }}
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                        @if(!$isAddMode && $selectedBooking)
                            @php
                                $bookingDateTime = $selectedBooking->date->copy()->setTime(
                                    $selectedBooking->start_time->hour,
                                    $selectedBooking->start_time->minute
                                );
                                $isPastBooking = $bookingDateTime->isPast();
                            @endphp

                            @if(!$isPastBooking)
                                <div class="flex flex-col gap-2 mt-6">
                                    <!-- Edit Button -->
                                    <button class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors" wire:click="edit({{ $selectedBooking->id }})">
                                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit Booking
                                    </button>
                                    <!-- Confirm Button (only show if not already confirmed) -->
                                    @if($selectedBooking->status->value !== 'confirmed')
                                        <button class="rounded bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors"
                                                x-on:click="showConfirm = true">
                                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Confirm Booking
                                        </button>
                                    @endif
                                    <!-- Cancel Button (only show if not already cancelled) -->
                                    @if($selectedBooking->status->value !== 'cancelled')
                                        <button class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 transition-colors"
                                                x-on:click="showCancel = true">
                                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            Cancel Booking
                                        </button>
                                    @endif
                                </div>
                            @else
                                <!-- Past Booking Notice -->
                                <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        <div>
                                            <div class="text-sm font-medium text-gray-700">Past Booking</div>
                                            <div class="text-xs text-gray-500">This booking has already passed. No actions can be performed.</div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        <!-- Restore Edit, Confirm, and Cancel Modals -->
                        <x-modal :show="'showEditModal'" :title="'Edit Booking'">
                            <form wire:submit.prevent="updateBooking" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <select wire:model.defer="editForm.status" class="mt-1 block w-full rounded border border-gray-300 p-2" required>
                                        <option value="pending">Pending</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                    @error('editForm.status') <span class="text-red-600 text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" wire:model.defer="editForm.is_light_required" id="is_light_required" class="rounded border-gray-300" />
                                    <label for="is_light_required" class="text-sm font-medium text-gray-700">Light Required</label>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                                    <textarea wire:model.defer="editForm.notes" class="mt-1 block w-full rounded border border-gray-300 p-2"></textarea>
                                </div>
                                <div class="flex justify-end gap-2 mt-4">
                                    <button type="button" wire:click="closeEditModal" class="px-4 py-2 rounded border">Cancel</button>
                                    <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white">Save</button>
                                </div>
                            </form>
                        </x-modal>
                        <x-modal :alpineShow="'showConfirm'" :title="'Confirm Booking'">
                            <p class="mb-4">Are you sure you want to confirm this booking for <strong>{{ $selectedBooking->tenant->name ?? '' }}</strong> on {{ $selectedBooking->date->format('d F Y') ?? '' }} at {{ $selectedBooking->start_time->format('H:i') ?? '' }}?</p>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="showConfirm = false" class="px-4 py-2 rounded border">Cancel</button>
                                <button type="button" wire:click="confirmBooking({{ $selectedBooking->id }})" @click="showConfirm = false" class="px-4 py-2 rounded bg-green-600 text-white">Confirm</button>
                            </div>
                        </x-modal>
                        <x-modal :alpineShow="'showCancel'" :title="'Cancel Booking'">
                            <p class="mb-4">Are you sure you want to cancel this booking for <strong>{{ $selectedBooking->tenant->name ?? '' }}</strong> on {{ $selectedBooking->date->format('d F Y') ?? '' }} at {{ $selectedBooking->start_time->format('H:i') ?? '' }}?</p>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="showCancel = false" class="px-4 py-2 rounded border">Keep Booking</button>
                                <button type="button" wire:click="openCancelModal({{ $selectedBooking->id }})" @click="showCancel = false" class="px-4 py-2 rounded bg-red-600 text-white">Cancel Booking</button>
                            </div>
                        </x-modal>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Add Booking Modal (Full Screen Overlay) -->
    <x-modal :show="'showAddModal'" :title="'Add Booking'">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-white p-0 m-0 w-screen h-screen overflow-auto">
            <form wire:submit.prevent="createBooking" class="w-full max-w-2xl mx-auto p-8 flex flex-col gap-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold">Add Booking</h2>
                    <button type="button" wire:click="closeAddModal" class="text-gray-400 hover:text-gray-700 text-2xl">&times;</button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Court</label>
                        <select wire:model="addForm.court_id" class="w-full p-3 border border-gray-300 rounded bg-white" required>
                            <option value="">Select Court</option>
                            @foreach($this->courts as $court)
                                <option value="{{ $court->id }}">{{ $court->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Tenant</label>
                        <select wire:model="addForm.tenant_id" class="w-full p-3 border border-gray-300 rounded bg-white" required>
                            <option value="">Select Tenant</option>
                            @foreach($this->tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Date</label>
                        <input type="date" wire:model="addForm.date" class="w-full p-3 border border-gray-300 rounded bg-white" min="{{ now()->format('Y-m-d') }}" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Time Slot</label>
                        <select wire:model="addForm.time_slot" class="w-full p-3 border border-gray-300 rounded bg-white" required>
                            <option value="">Select Time Slot</option>
                            @foreach($availableTimeSlots as $slot)
                                <option value="{{ $slot['value'] }}" @if($slot['disabled']) disabled @endif>
                                    {{ $slot['label'] }} {{ $slot['type_label'] }} {{ $slot['peak_label'] }}
                                </option>
                            @endforeach
                        </select>
                        @if($addForm['date'])
                            @php
                                $selectedDate = \Carbon\Carbon::parse($addForm['date']);
                                $bookingType = $this->getDateBookingType($selectedDate);
                                $isToday = $selectedDate->isToday();
                            @endphp
                            <div class="mt-2 text-sm">
                                @if($isToday)
                                    <span class="text-orange-600">⚠️ Only future time slots available for today</span>
                                @elseif($bookingType === 'free')
                                    <span class="text-green-600">🆓 Free booking available for this date</span>
                                @elseif($bookingType === 'premium')
                                    <span class="text-purple-600">⭐ Premium booking available for this date</span>
                                @else
                                    <span class="text-gray-600">🔒 No booking available for this date</span>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-2">Notes</label>
                        <textarea wire:model="addForm.notes" class="w-full p-3 border border-gray-300 rounded bg-white"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" wire:click="closeAddModal" class="px-4 py-2 rounded border">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white">Create Booking</button>
                </div>
                @if($addError)
                    <div class="text-red-600 text-sm mt-2">{{ $addError }}</div>
                @endif
            </form>
        </div>
    </x-modal>

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
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                            @if($bookingToCancel->booking_type === 'free') bg-blue-100 text-blue-800 @else bg-purple-100 text-purple-800 @endif">
                            @if($bookingToCancel->booking_type === 'free') 🆓 Free @else ⭐ Premium @endif
                        </span>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                            @if($bookingToCancel->status === BookingStatusEnum::CONFIRMED) bg-green-100 text-green-800 @else bg-orange-100 text-orange-800 @endif">
                            @if($bookingToCancel->status === BookingStatusEnum::CONFIRMED) ✅ Confirmed @else ⏳ Pending @endif
                        </span>
                    </div>

                    <div class="mt-3">
                        <p class="text-sm text-gray-600">
                            <strong>Tenant:</strong> {{ $bookingToCancel->tenant->name }}
                        </p>
                        <p class="text-sm text-gray-600">
                            <strong>Reference:</strong> #{{ $bookingToCancel->booking_reference }}
                        </p>
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
                                <p>• The tenant's quota will be restored</p>
                                <p>• The court will be available for other bookings</p>
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

    <!-- Export Modal -->
    @if($showExportModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Export Bookings Report
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Generate detailed reports in Excel or PDF format
                                </p>
                            </div>
                        </div>
                        <button wire:click="closeExportModal" class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                            <span class="sr-only">Close</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Export Form -->
                    <form wire:submit.prevent="exportBookings" class="space-y-6">
                        <!-- Date Range -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Date Range</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="export-date-from" class="block text-sm font-medium text-gray-700 mb-1">From</label>
                                    <input
                                        id="export-date-from"
                                        type="date"
                                        wire:model="exportDateFrom"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
                                        required
                                    >
                                </div>
                                <div>
                                    <label for="export-date-to" class="block text-sm font-medium text-gray-700 mb-1">To</label>
                                    <input
                                        id="export-date-to"
                                        type="date"
                                        wire:model="exportDateTo"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
                                        required
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Filters</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="export-status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                    <select
                                        id="export-status"
                                        wire:model="exportStatusFilter"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
                                    >
                                        <option value="">All Statuses</option>
                                        <option value="pending">Pending</option>
                                        <option value="confirmed">Confirmed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="export-court" class="block text-sm font-medium text-gray-700 mb-1">Court</label>
                                    <select
                                        id="export-court"
                                        wire:model="exportCourtFilter"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
                                    >
                                        <option value="">All Courts</option>
                                        @foreach($this->courts as $court)
                                            <option value="{{ $court->id }}">{{ $court->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="export-type" class="block text-sm font-medium text-gray-700 mb-1">Booking Type</label>
                                    <select
                                        id="export-type"
                                        wire:model="exportBookingTypeFilter"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
                                    >
                                        <option value="">All Types</option>
                                        <option value="free">Free</option>
                                        <option value="premium">Premium</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Export Format -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">Export Format</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="relative flex cursor-pointer rounded-lg border border-gray-300 bg-white p-4 shadow-sm focus:outline-none hover:bg-gray-50">
                                    <input type="radio" wire:model="exportFormat" value="excel" class="sr-only" aria-labelledby="excel-label" aria-describedby="excel-description">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span id="excel-label" class="block text-sm font-medium text-gray-900">Excel (.xlsx)</span>
                                            <span id="excel-description" class="mt-1 flex items-center text-sm text-gray-500">
                                                <svg class="mr-2 h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Spreadsheet format with formatting
                                            </span>
                                        </span>
                                    </span>
                                    <span class="pointer-events-none absolute -inset-px rounded-lg border-2" :class="{'border-purple-500': exportFormat === 'excel', 'border-transparent': exportFormat !== 'excel'}"></span>
                                </label>

                                <label class="relative flex cursor-pointer rounded-lg border border-gray-300 bg-white p-4 shadow-sm focus:outline-none hover:bg-gray-50">
                                    <input type="radio" wire:model="exportFormat" value="pdf" class="sr-only" aria-labelledby="pdf-label" aria-describedby="pdf-description">
                                    <span class="flex flex-1">
                                        <span class="flex flex-col">
                                            <span id="pdf-label" class="block text-sm font-medium text-gray-900">PDF (.pdf)</span>
                                            <span id="pdf-description" class="mt-1 flex items-center text-sm text-gray-500">
                                                <svg class="mr-2 h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                Print-ready document format
                                            </span>
                                        </span>
                                    </span>
                                    <span class="pointer-events-none absolute -inset-px rounded-lg border-2" :class="{'border-purple-500': exportFormat === 'pdf', 'border-transparent': exportFormat !== 'pdf'}"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Export Preview -->
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <h4 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                <svg class="mr-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                Export Preview
                            </h4>
                            <div class="text-sm text-gray-600 space-y-2">
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-purple-500 rounded-full mr-2"></span>
                                    <span class="font-medium">Date Range:</span>
                                    <span class="ml-1">{{ $exportDateFrom ? \Carbon\Carbon::parse($exportDateFrom)->format('M j, Y') : 'Not set' }} - {{ $exportDateTo ? \Carbon\Carbon::parse($exportDateTo)->format('M j, Y') : 'Not set' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span>
                                    <span class="font-medium">Status:</span>
                                    <span class="ml-1">{{ $exportStatusFilter ? ucfirst($exportStatusFilter) : 'All' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                    <span class="font-medium">Court:</span>
                                    <span class="ml-1">{{ $exportCourtFilter ? ($this->courts->firstWhere('id', $exportCourtFilter)->name ?? 'All') : 'All' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-yellow-500 rounded-full mr-2"></span>
                                    <span class="font-medium">Type:</span>
                                    <span class="ml-1">{{ $exportBookingTypeFilter ? ucfirst($exportBookingTypeFilter) : 'All' }}</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                    <span class="font-medium">Format:</span>
                                    <span class="ml-1">{{ strtoupper($exportFormat) }}</span>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Action Buttons -->
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button
                        type="submit"
                        wire:click="exportBookings"
                        wire:loading.attr="disabled"
                        @class([
                            'w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm transition-colors',
                            'bg-purple-600 hover:bg-purple-700 focus:ring-purple-500 cursor-pointer' => !$isExporting,
                            'bg-gray-400 focus:ring-gray-500 cursor-not-allowed' => $isExporting,
                        ])
                        @if($isExporting) disabled @endif>
                        @if($isExporting)
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Exporting...
                        @else
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Export Report
                        @endif
                    </button>
                    <button
                        type="button"
                        wire:click="closeExportModal"
                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Floating Action Button for Mobile -->
    <div class="fixed bottom-6 right-6 z-40 lg:hidden">
        <div class="flex flex-col space-y-3">
            <!-- Export FAB -->
            <button
                wire:click="openExportModal"
                class="flex items-center justify-center w-14 h-14 bg-purple-600 text-white rounded-full shadow-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-all duration-200 transform hover:scale-110"
                title="Export Report">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </button>

            <!-- Add Booking FAB -->
            <button
                wire:click="openAddModal"
                class="flex items-center justify-center w-14 h-14 bg-blue-600 text-white rounded-full shadow-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-110"
                title="Add Booking">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
            </button>
        </div>
    </div>

@script
    <script>
        // Modal backdrop click to close
        document.addEventListener('click', function(event) {
            if (event.target.id === 'cancelModal') {
                @this.closeCancelModal();
            }
            if (event.target.id === 'exportModal') {
                @this.closeExportModal();
            }
        });

        // ESC key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && @this.showCancelModal) {
                @this.closeCancelModal();
            }
            if (event.key === 'Escape' && @this.showExportModal) {
                @this.closeExportModal();
            }
        });
    </script>
@endscript
</div>
