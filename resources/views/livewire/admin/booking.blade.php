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

    // Pagination
    protected $paginationTheme = 'bootstrap';

    public $viewMode = 'table'; // 'table' or 'weekly'

    public $weekStart;

    public $weekPicker = '';

    // Cache properties for optimization
    protected $cachedBookings = null;

    protected $cachedStats = null;

    protected $cachedCourts = null;

    public function mount()
    {
        $this->weekStart = now()->startOfWeek()->format('Y-m-d');
        $this->weekPicker = $this->weekStart;
    }

    public function updated($property)
    {
        // Reset pagination when any filter changes
        if (in_array($property, ['search', 'statusFilter', 'dateFilter', 'courtFilter', 'excludeCancelled'])) {
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

    public function cancelBooking($bookingId)
    {
        $booking = Booking::find($bookingId);
        if ($booking) {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_by' => auth('admin')->id(),
                'cancelled_at' => now(),
            ]);
            $this->selectedBooking = $booking->fresh(['tenant', 'court']);
            $this->clearCache();
            session()->flash('message', 'Booking cancelled successfully.');
        }
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
            'timeSlots' => array_map(fn ($slot) => $slot[0].' - '.$slot[1], $timeSlots),
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
        $timeSlots = [];
        $startTime = Carbon::createFromTime(8, 0, 0);
        $endTime = Carbon::createFromTime(22, 0, 0);
        $slotDuration = 1;

        while ($startTime < $endTime) {
            $slotEnd = $startTime->copy()->addHours($slotDuration);
            $timeSlots[] = [
                $startTime->format('H:i'),
                $slotEnd->format('H:i'),
            ];
            $startTime->addHours($slotDuration);
        }

        return $timeSlots;
    }

    private function generateWeekStructure($startOfWeek, $endOfWeek, $timeSlots)
    {
        $grouped = collect();
        for ($date = $startOfWeek->copy(); $date->lte($endOfWeek); $date->addDay()) {
            $day = $date->format('Y-m-d');
            $grouped[$day] = collect();
            foreach ($timeSlots as $slot) {
                $slotLabel = $slot[0].' - '.$slot[1];
                $grouped[$day][$slotLabel] = collect();
            }
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
}

?>

<div class="mx-auto max-w-7xl p-8">
    <!-- Left Column: Dashboard, Filters, Bookings Table -->
    <div>
        <!-- Dashboard Stats -->
        <div class="mb-6 grid grid-cols-3 gap-4">
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
        </div>

        <!-- New Booking Button -->
        <div class="mb-6 flex justify-end">
            <a href="{{ route('admin.booking.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                New Booking
            </a>
        </div>

        <!-- Court Filter Tabs -->
        <div class="mb-4 flex gap-2 overflow-x-auto pb-2">
            <button wire:click="filterByCourt('')" class="px-4 py-2 rounded font-medium focus:outline-none whitespace-nowrap {{ $courtFilter === '' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">All</button>
            @foreach($this->courts as $court)
                <button wire:click="filterByCourt('{{ $court->id }}')" class="px-4 py-2 rounded font-medium focus:outline-none whitespace-nowrap {{ $courtFilter == $court->id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $court->name }}</button>
            @endforeach
        </div>

        <!-- View Toggle -->
        <div class="mb-4 flex gap-2">
            <button wire:click="setViewMode('table')" class="px-4 py-2 rounded font-medium focus:outline-none {{ $viewMode === 'table' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">Table View</button>
            <button wire:click="setViewMode('weekly')" class="px-4 py-2 rounded font-medium focus:outline-none {{ $viewMode === 'weekly' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-700' }}">Weekly View</button>
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
                                            <tr wire:click="showDetail({{ $booking->id }})" class="hover:bg-blue-50 cursor-pointer">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="font-semibold text-gray-900">{{ $booking->tenant->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $booking->tenant->email }}</div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->booking_reference }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->court->name ?? '-' }}</td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $booking->date->format('Y-m-d') }}</td>
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
                                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $colorClass }}">
                                                        {{ ucfirst($booking->status->value) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                        <div class="p-4">
                            {{ $this->bookings->links() }}
                        </div>
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
                                <div class="flex-1 min-w-[180px]">
                                    <div class="text-xs font-bold text-gray-500 mb-2 text-center border-b pb-1">{{ \Carbon\Carbon::parse($date)->format('D, d M') }}</div>
                                    <div class="flex flex-col gap-2 min-h-[120px]">
                                        @foreach($this->weeklyBookings['timeSlots'] as $slotLabel)
                                            <div class="mb-2">
                                                <div class="text-[11px] text-gray-400 font-semibold mb-1">{{ $slotLabel }}</div>
                                                @forelse($slots[$slotLabel] as $booking)
                                                    <div wire:click="showDetail({{ $booking->id }})" class="rounded-lg border p-2 bg-gray-50 shadow-sm mb-1 hover:bg-gray-100 cursor-pointer transition-colors">
                                                        <div class="flex items-center gap-2 mb-1">
                                                            <span class="inline-block bg-blue-100 text-blue-800 text-[10px] font-bold rounded px-2 py-0.5">{{ $booking->court->name ?? '-' }}</span>
                                                            <div class="font-semibold text-gray-800 text-xs">{{ $booking->tenant->name }}</div>
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
                                                            <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $colorClass }}">
                                                                {{ ucfirst($booking->status->value) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                @empty
                                                    <div class="rounded-lg border border-dashed p-2 bg-gray-50 text-gray-400 text-xs text-center mb-1">No bookings</div>
                                                @endforelse
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
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
                            <div class="flex items-center justify-between mb-6">
                                <div>
                                    <div class="text-xs text-gray-400">Prepared for</div>
                                    <div class="font-bold text-lg text-gray-800">{{ $selectedBooking->tenant->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $selectedBooking->tenant->email }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-400">Date</div>
                                    <div class="font-semibold text-gray-700">{{ $selectedBooking->date->format('d F, Y') }}</div>
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
                                <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $colorClass }}">
                                    {{ ucfirst($selectedBooking->status->value) }}
                                </span>
                            </div>
                            <div class="mb-4">
                                <div class="text-xs text-gray-400 mb-1">Type</div>
                                <div class="font-semibold text-gray-700">{{ ucfirst($selectedBooking->booking_type) }}</div>
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
                        </div>
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

                        <!-- Edit Modal -->
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

                        <!-- Confirm Modal -->
                        <x-modal :alpineShow="'showConfirm'" :title="'Confirm Booking'">
                            <p class="mb-4">Are you sure you want to confirm this booking for <strong>{{ $selectedBooking->tenant->name ?? '' }}</strong> on {{ $selectedBooking->date->format('d F Y') ?? '' }} at {{ $selectedBooking->start_time->format('H:i') ?? '' }}?</p>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="showConfirm = false" class="px-4 py-2 rounded border">Cancel</button>
                                <button type="button" wire:click="confirmBooking({{ $selectedBooking->id }})" @click="showConfirm = false" class="px-4 py-2 rounded bg-green-600 text-white">Confirm</button>
                            </div>
                        </x-modal>

                        <!-- Cancel Modal -->
                        <x-modal :alpineShow="'showCancel'" :title="'Cancel Booking'">
                            <p class="mb-4">Are you sure you want to cancel this booking for <strong>{{ $selectedBooking->tenant->name ?? '' }}</strong> on {{ $selectedBooking->date->format('d F Y') ?? '' }} at {{ $selectedBooking->start_time->format('H:i') ?? '' }}?</p>
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="showCancel = false" class="px-4 py-2 rounded border">Keep Booking</button>
                                <button type="button" wire:click="cancelBooking({{ $selectedBooking->id}})" @click="showCancel = false" class="px-4 py-2 rounded bg-red-600 text-white">Cancel Booking</button>
                            </div>
                        </x-modal>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
