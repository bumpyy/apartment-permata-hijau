<?php

use Carbon\Carbon;
use App\Models\Booking;
use App\Models\Court;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.frontend.app')] class extends Component {
    public $currentMonth;
    public $currentYear;
    public $calendarDays = [];
    public $bookings = [];
    public $courts = [];
    public $selectedCourt = null;
    public $selectedDate = null;
    public $selectedDateBookings = [];
    public $showDayDetail = false;
    public $isLoading = true;
    public $viewMode = 'month'; // 'month' or 'week'
    public $weekStart = null;

    public function mount($viewMode = 'month', $date = null)
    {
        $this->viewMode = $viewMode;

        if ($date) {
            $selectedDate = Carbon::parse($date);
            $this->currentMonth = $selectedDate->month;
            $this->currentYear = $selectedDate->year;
            $this->weekStart = $selectedDate->startOfWeek();
        } else {
            $this->currentMonth = Carbon::now()->month;
            $this->currentYear = Carbon::now()->year;
            $this->weekStart = Carbon::now()->startOfWeek();
        }

        $this->courts = Court::where('is_active', true)->get();
        $this->selectedCourt = $this->courts[0]->id ?? null;
        $this->generateCalendar();
        $this->loadBookings();
        $this->isLoading = false;
    }

    public function generateCalendar()
    {
        $this->calendarDays = [];

        if ($this->viewMode === 'month') {
            $this->generateMonthCalendar();
        } else {
            $this->generateWeekCalendar();
        }
    }

    public function generateMonthCalendar()
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1);
        $daysInMonth = $date->daysInMonth;

        // Get the first day of the month and adjust for the start of the week
        $firstDayOfMonth = $date->copy()->firstOfMonth();
        $lastDayOfMonth = $date->copy()->lastOfMonth();

        // Start from the previous month's days that appear in the first week
        $startDay = $firstDayOfMonth->copy()->startOfWeek();

        // End with the next month's days that appear in the last week
        $endDay = $lastDayOfMonth->copy()->endOfWeek();

        $currentDay = $startDay->copy();

        $weeks = [];
        $week = [];

        while ($currentDay <= $endDay) {
            $dayData = [
                'date' => $currentDay->format('Y-m-d'),
                'day' => $currentDay->day,
                'isCurrentMonth' => $currentDay->month === $date->month,
                'isToday' => $currentDay->isToday(),
                'isPast' => $currentDay->isPast() && !$currentDay->isToday(),
                'isWeekend' => $currentDay->isWeekend(),
                'dayOfWeek' => $currentDay->dayOfWeek,
                'formattedDate' => $currentDay->format('M j'),
                'bookings' => []
            ];

            $week[] = $dayData;

            // If we've reached the end of the week, start a new week
            if ($currentDay->dayOfWeek === Carbon::SUNDAY) {
                $weeks[] = $week;
                $week = [];
            }

            $currentDay->addDay();
        }

        // Add the last week if it's not empty
        if (!empty($week)) {
            $weeks[] = $week;
        }

        $this->calendarDays = $weeks;
    }

    public function generateWeekCalendar()
    {
        $startDay = $this->weekStart->copy();
        $endDay = $startDay->copy()->addDays(6);

        $week = [];
        $currentDay = $startDay->copy();

        while ($currentDay <= $endDay) {
            $week[] = [
                'date' => $currentDay->format('Y-m-d'),
                'day' => $currentDay->day,
                'isCurrentMonth' => $currentDay->month === Carbon::now()->month,
                'isToday' => $currentDay->isToday(),
                'isPast' => $currentDay->isPast() && !$currentDay->isToday(),
                'isWeekend' => $currentDay->isWeekend(),
                'dayOfWeek' => $currentDay->dayOfWeek,
                'formattedDate' => $currentDay->format('M j'),
                'dayName' => $currentDay->format('D'),
                'bookings' => []
            ];

            $currentDay->addDay();
        }

        $this->calendarDays = [$week];
    }

    public function loadBookings()
    {
        if (!$this->selectedCourt) {
            return;
        }

        $startDate = $this->viewMode === 'month'
            ? Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->startOfMonth()->startOfWeek()
            : $this->weekStart;

        $endDate = $this->viewMode === 'month'
            ? Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->endOfMonth()->endOfWeek()
            : $this->weekStart->copy()->addDays(6);

        $bookings = Booking::with('tenant')
            ->where('court_id', $this->selectedCourt)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $this->bookings = $bookings;

        // Group bookings by date
        $bookingsByDate = [];
        foreach ($bookings as $booking) {
            $date = $booking->date->format('Y-m-d');
            if (!isset($bookingsByDate[$date])) {
                $bookingsByDate[$date] = [];
            }
            $bookingsByDate[$date][] = $booking;
        }

        // Add bookings to calendar days
        if ($this->viewMode === 'month') {
            foreach ($this->calendarDays as &$week) {
                foreach ($week as &$day) {
                    $date = $day['date'];
                    $day['bookings'] = $bookingsByDate[$date] ?? [];
                    $day['hasBookings'] = !empty($day['bookings']);
                    $day['bookingCount'] = count($day['bookings']);
                }
            }
        } else {
            foreach ($this->calendarDays[0] as &$day) {
                $date = $day['date'];
                $day['bookings'] = $bookingsByDate[$date] ?? [];
                $day['hasBookings'] = !empty($day['bookings']);
                $day['bookingCount'] = count($day['bookings']);
            }
        }
    }

    public function previousMonth()
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->subMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->generateCalendar();
        $this->loadBookings();
    }

    public function nextMonth()
    {
        $date = Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->addMonth();
        $this->currentMonth = $date->month;
        $this->currentYear = $date->year;
        $this->generateCalendar();
        $this->loadBookings();
    }

    public function previousWeek()
    {
        $this->weekStart = $this->weekStart->copy()->subWeek();
        $this->generateCalendar();
        $this->loadBookings();
    }

    public function nextWeek()
    {
        $this->weekStart = $this->weekStart->copy()->addWeek();
        $this->generateCalendar();
        $this->loadBookings();
    }

    public function currentPeriod()
    {
        if ($this->viewMode === 'month') {
            $this->currentMonth = Carbon::now()->month;
            $this->currentYear = Carbon::now()->year;
        } else {
            $this->weekStart = Carbon::now()->startOfWeek();
        }

        $this->generateCalendar();
        $this->loadBookings();
    }

    public function toggleViewMode()
    {
        $this->viewMode = $this->viewMode === 'month' ? 'week' : 'month';

        if ($this->viewMode === 'week' && !$this->weekStart) {
            $this->weekStart = Carbon::now()->startOfWeek();
        }

        $this->generateCalendar();
        $this->loadBookings();
    }

    public function selectCourt($courtId)
    {
        $this->selectedCourt = $courtId;
        $this->loadBookings();
    }

    public function viewDayDetail($date)
    {
        $this->selectedDate = $date;
        $this->selectedDateBookings = collect($this->bookings)
            ->filter(function ($booking) use ($date) {
                return $booking->date->format('Y-m-d') === $date;
            })
            ->sortBy('start_time')
            ->values()
            ->all();

        $this->showDayDetail = true;
    }

    public function closeDayDetail()
    {
        $this->showDayDetail = false;
        $this->selectedDate = null;
        $this->selectedDateBookings = [];
    }

    public function getFormattedMonthYearProperty()
    {
        return Carbon::createFromDate($this->currentYear, $this->currentMonth, 1)->format('F Y');
    }

    public function getFormattedWeekRangeProperty()
    {
        $endOfWeek = $this->weekStart->copy()->addDays(6);

        if ($this->weekStart->month === $endOfWeek->month) {
            return $this->weekStart->format('F j') . ' - ' . $endOfWeek->format('j, Y');
        } else if ($this->weekStart->year === $endOfWeek->year) {
            return $this->weekStart->format('F j') . ' - ' . $endOfWeek->format('F j, Y');
        } else {
            return $this->weekStart->format('F j, Y') . ' - ' . $endOfWeek->format('F j, Y');
        }
    }

    public function getFormattedSelectedDateProperty()
    {
        if (!$this->selectedDate) {
            return '';
        }

        return Carbon::parse($this->selectedDate)->format('l, F j, Y');
    }

    public function goToBooking($date)
    {
        return redirect()->route('facilities', ['date' => $date]);
    }

    public function setViewMode($mode)
    {
        $this->viewMode = $mode;

        if ($mode === 'day' && !$this->selectedDate) {
            $this->selectedDate = Carbon::now()->format('Y-m-d');
        }

        $this->generateCalendar();
        $this->loadBookings();
    }
}; ?>

<div class="booking-calendar-container animate-in fade-in duration-500">
    <!-- Loading Overlay -->
    @if($isLoading)
    <div class="absolute inset-0 bg-white bg-opacity-80 flex items-center justify-center z-50">
        <div class="flex flex-col items-center">
            <div class="w-16 h-16 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
            <p class="mt-4 text-blue-600 font-medium">Loading calendar...</p>
        </div>
    </div>
    @endif

    <!-- Calendar Header -->
    <div class="calendar-header bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-4 animate-in slide-in-from-top duration-500">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <!-- Month/Week Navigation -->
            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2">
                    <button
                        wire:click="{{ $viewMode === 'month' ? 'previousMonth' : 'previousWeek' }}"
                        class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors duration-200 transform hover:scale-105 active:scale-95"
                        title="{{ $viewMode === 'month' ? 'Previous Month' : 'Previous Week' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <button
                        wire:click="currentPeriod"
                        class="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 transition-colors duration-200 transform hover:scale-105 active:scale-95"
                        title="{{ $viewMode === 'month' ? 'Current Month' : 'Current Week' }}">
                        Today
                    </button>

                    <button
                        wire:click="{{ $viewMode === 'month' ? 'nextMonth' : 'nextWeek' }}"
                        class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 transition-colors duration-200 transform hover:scale-105 active:scale-95"
                        title="{{ $viewMode === 'month' ? 'Next Month' : 'Next Week' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <h2 class="text-xl font-bold">
                    {{ $viewMode === 'month' ? $this->formattedMonthYear : $this->formattedWeekRange }}
                </h2>
            </div>

            <!-- View Mode Toggle & Court Selection -->
            <div class="flex items-center space-x-4">
                <button
                    wire:click="toggleViewMode"
                    class="px-4 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition-colors duration-200 transform hover:scale-105 active:scale-95 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                    </svg>
                    {{ $viewMode === 'month' ? 'Week View' : 'Month View' }}
                </button>

                <button
                    wire:click="setViewMode('day')"
                    class="px-4 py-2 rounded-md bg-gray-100 hover:bg-gray-200 transition-colors duration-200 transform hover:scale-105 active:scale-95 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Day View
                </button>

                <div class="relative">
                    <select
                        wire:model.live="selectedCourt"
                        class="pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                        @foreach($courts as $court)
                        <option value="{{ $court->id }}">Court {{ $court->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="text-sm text-gray-600">
            @if($viewMode === 'month')
            {{ Carbon::createFromDate($currentYear, $currentMonth, 1)->format('F 1, Y') }} -
            {{ Carbon::createFromDate($currentYear, $currentMonth, 1)->endOfMonth()->format('F j, Y') }}
            @elseif($viewMode === 'week')
            {{ $weekStart->format('F j, Y') }} - {{ $weekStart->copy()->addDays(6)->format('F j, Y') }}
            @else
            {{ Carbon::parse($selectedDate)->format('F j, Y') }}
            @endif
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="calendar-grid bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden animate-in fade-in duration-700 delay-200">
        <!-- Day Headers -->
        <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-200">
            @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
            <div class="p-2 text-center font-medium {{ $dayName === 'Sun' || $dayName === 'Sat' ? 'text-red-500' : 'text-gray-700' }}">
                {{ $dayName }}
            </div>
            @endforeach
        </div>

        <!-- Month View -->
        @if($viewMode === 'month')
        <div class="grid grid-cols-7 auto-rows-fr">
            @foreach($calendarDays as $week)
            @foreach($week as $day)
            <div
                wire:click="viewDayDetail('{{ $day['date'] }}')"
                @class([ 'min-h-[100px] p-2 border-b border-r border-gray-200 transition-all duration-200 hover:bg-blue-50 cursor-pointer' , 'bg-gray-100'=> !$day['isCurrentMonth'],
                'bg-blue-50' => $day['isToday'],
                'opacity-60' => $day['isPast'],
                'border-l' => $day['dayOfWeek'] === 0, // Sunday
                ])
                >
                <div class="flex justify-between items-start">
                    <span @class([ 'inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-medium' , 'bg-blue-600 text-white'=> $day['isToday'],
                        'text-gray-400' => !$day['isCurrentMonth'] && !$day['isToday'],
                        'text-gray-900' => $day['isCurrentMonth'] && !$day['isToday'],
                        'text-red-500' => $day['isWeekend'] && !$day['isToday'] && $day['isCurrentMonth'],
                        ])>
                        {{ $day['day'] }}
                    </span>

                    @if($day['hasBookings'])
                    <span class="inline-flex items-center justify-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ $day['bookingCount'] }}
                    </span>
                    @endif
                </div>

                <!-- Booking Indicators -->
                @if($day['hasBookings'])
                <div class="mt-2 space-y-1">
                    @foreach(array_slice($day['bookings'], 0, 2) as $booking)
                    <div @class([ 'text-xs px-2 py-1 rounded truncate' , 'bg-green-100 text-green-800'=> $booking->status === 'confirmed',
                        'bg-yellow-100 text-yellow-800' => $booking->status === 'pending',
                        ])>
                        {{ $booking->start_time->format('H:i') }} - {{ substr($booking->tenant->display_name, 0, 10) }}{{ strlen($booking->tenant->display_name) > 10 ? '...' : '' }}
                    </div>
                    @endforeach

                    @if(count($day['bookings']) > 2)
                    <div class="text-xs text-gray-500 font-medium">
                        +{{ count($day['bookings']) - 2 }} more
                    </div>
                    @endif
                </div>
                @endif
            </div>
            @endforeach
            @endforeach
        </div>
        @else
        <!-- Week View -->
        <div class="grid grid-cols-7 auto-rows-fr">
            @foreach($calendarDays[0] as $day)
            <div
                wire:click="viewDayDetail('{{ $day['date'] }}')"
                @class([ 'min-h-[200px] p-3 border-r border-gray-200 transition-all duration-200 hover:bg-blue-50 cursor-pointer' , 'bg-blue-50'=> $day['isToday'],
                'opacity-60' => $day['isPast'],
                'border-l' => $day['dayOfWeek'] === 0, // Sunday
                ])
                >
                <div class="flex flex-col items-center mb-3">
                    <span class="text-sm font-medium text-gray-500">{{ $day['dayName'] }}</span>
                    <span @class([ 'inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-medium' , 'bg-blue-600 text-white'=> $day['isToday'],
                        'text-gray-900' => !$day['isToday'],
                        'text-red-500' => $day['isWeekend'] && !$day['isToday'],
                        ])>
                        {{ $day['day'] }}
                    </span>
                </div>

                <!-- Booking Indicators -->
                @if($day['hasBookings'])
                <div class="space-y-2">
                    @foreach($day['bookings'] as $booking)
                    <div @class([ 'text-xs px-2 py-1.5 rounded' , 'bg-green-100 text-green-800'=> $booking->status === 'confirmed',
                        'bg-yellow-100 text-yellow-800' => $booking->status === 'pending',
                        ])>
                        <div class="font-medium">{{ $booking->start_time->format('H:i') }} - {{ $booking->end_time->format('H:i') }}</div>
                        <div class="truncate">{{ $booking->tenant->display_name }}</div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="flex items-center justify-center h-full opacity-30">
                    <span class="text-sm text-gray-400">No bookings</span>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Day Detail Modal -->
    @if($showDayDetail)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 animate-in fade-in duration-300">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full mx-4 animate-in zoom-in duration-300">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">{{ $this->formattedSelectedDate }}</h3>
                <button
                    wire:click="closeDayDetail"
                    class="p-2 rounded-full hover:bg-gray-100 transition-colors duration-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mb-4">
                <h4 class="font-medium text-gray-700 mb-2">Court {{ $courts->firstWhere('id', $selectedCourt)->name ?? '' }}</h4>

                @if(count($selectedDateBookings) > 0)
                <div class="space-y-3 max-h-80 overflow-y-auto">
                    @foreach($selectedDateBookings as $booking)
                    <div @class([ 'p-3 rounded-lg border' , 'bg-green-50 border-green-200'=> $booking->status === 'confirmed',
                        'bg-yellow-50 border-yellow-200' => $booking->status === 'pending',
                        ])>
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-medium">{{ $booking->start_time->format('H:i') }} - {{ $booking->end_time->format('H:i') }}</div>
                                <div class="text-sm text-gray-600">{{ $booking->tenant->display_name }}</div>
                                @if($booking->is_light_required)
                                <div class="text-xs text-orange-600 mt-1">💡 Court lights (+IDR 50k)</div>
                                @endif
                            </div>
                            <span @class([ 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium' , 'bg-green-100 text-green-800'=> $booking->status === 'confirmed',
                                'bg-yellow-100 text-yellow-800' => $booking->status === 'pending',
                                ])>
                                {{ ucfirst($booking->status) }}
                            </span>
                        </div>

                        @if($booking->booking_reference)
                        <div class="text-xs text-gray-500 mt-2">Ref: #{{ $booking->booking_reference }}</div>
                        @endif
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-8 text-gray-500">
                    No bookings for this day
                </div>
                @endif
            </div>

            <div class="flex justify-between">
                <button
                    wire:click="closeDayDetail"
                    class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                    Close
                </button>

                <button
                    wire:click="goToBooking('{{ $selectedDate }}')"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200">
                    Book This Day
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
