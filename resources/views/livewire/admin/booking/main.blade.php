<?php

namespace App\Http\Livewire\Admin;

use App\Enum\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
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
}

?>

<div class="mx-auto max-w-7xl p-8">
    <!-- Left Column: Dashboard, Filters, Bookings Table -->
    <div>
        <!-- Dashboard Stats -->
        @include('livewire.admin.booking.ui.stats')

        <!-- Todays Bookings Section -->
        @include('livewire.admin.booking.ui.todays-bookings')

        <!-- Upcoming Bookings Preview -->
        @include('livewire.admin.booking.ui.upcoming-bookings-preview')

        <!-- Court Filter Tabs -->
        @include('livewire.admin.booking.ui.court-filter-tabs')

        <!-- View Toggle and Export -->
        @include('livewire.admin.booking.ui.view-toggle')

        <div class="flex flex-col lg:flex-row relative gap-6">
            <div class="flex-1 min-w-0">
                @if($viewMode === 'table')
                    @include('livewire.admin.booking.views.table-view')
                @else
                    @include('livewire.admin.booking.views.calendar-view')
                @endif
            </div>

            @include('livewire.admin.booking.ui.detail-panel')
        </div>
    </div>

    <!-- Cancellation Confirmation Modal -->
    @include('livewire.admin.booking.modals.cancellation-confirmation')

    <!-- Export Modal -->
    @include('livewire.admin.booking.modals.export')


</div>
