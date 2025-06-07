<?php

use function Livewire\Volt\{layout, state, mount};
use Carbon\Carbon;

state([
    'courtNumber' => 2,
    'startDate' => '',
    'endDate' => '',
    'weekDays' => [],
    'timeSlots' => [],
    'bookedSlots' => [],
    'preliminaryBookedSlots' => [],
    'selectedSlots' => [],
]);
layout('components.frontend.app');


mount(function () {
    $today = Carbon::today();
    $this->startDate = $today->format('d/m/Y');
    $this->endDate = $today->copy()->addDays(6)->format('d/m/Y');

    // Generate week days
    $days = ['TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN', 'MON'];
    $currentDate = $today->copy();

    for ($i = 0; $i < 7; $i++) {
        $this->weekDays[] = [
            'name' => $days[$i],
            'date' => $currentDate->format('Y-m-d'),
        ];
        $currentDate->addDay();
    }

    // Generate time slots (8 AM to 11 PM)
    for ($hour = 8; $hour < 23; $hour++) {
        $this->timeSlots[] = [
            'start' => sprintf('%02d:00', $hour),
            'end' => sprintf('%02d:00', $hour + 1),
        ];
    }

    // Sample booked slots
    $this->bookedSlots = [
        '2024-12-17-10:00',
        '2024-12-18-14:00',
        '2024-12-19-16:00',
    ];

    $this->preliminaryBookedSlots = [
        '2024-12-17-18:00',
        '2024-12-18-19:00',
        '2024-12-20-11:00',
    ];
});

$toggleTimeSlot = function ($slotKey) {
    if (in_array($slotKey, $this->bookedSlots) || in_array($slotKey, $this->preliminaryBookedSlots)) {
        return;
    }

    $index = array_search($slotKey, $this->selectedSlots);
    if ($index !== false) {
        unset($this->selectedSlots[$index]);
        $this->selectedSlots = array_values($this->selectedSlots);
    } else {
        $this->selectedSlots[] = $slotKey;
    }
};

$confirmBooking = function () {
    if (count($this->selectedSlots) === 0) {
        return;
    }

    $this->bookedSlots = array_merge($this->bookedSlots, $this->selectedSlots);
    $this->selectedSlots = [];

    session()->flash('message', 'Booking confirmed successfully!');
};

?>

<div class="">
    <x-facilities.banner />
    <!-- Header -->


    <!-- Content -->
    <div class="p-6 container bg-white">
        <!-- Title Section -->
        <div class="mb-6">
            <div class="flex justify-between items-start flex-wrap gap-4">
                <div>
                    <h2 class="text-lg mb-1 font-medium">Select Date & Time</h2>
                    <h3 class="text-2xl font-bold mb-1">Free Booking, Court {{ $courtNumber }}</h3>
                </div>
                <p class="text-sm text-gray-600">{{ $startDate }} - {{ $endDate }}</p>
            </div>
        </div>

        <!-- Success Message -->
        @if (session()->has('message'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
            {{ session('message') }}
        </div>
        @endif

        <!-- Booking Table -->
        <div class="overflow-x-auto mb-6 border border-gray-300 rounded-lg">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        @foreach($weekDays as $day)
                        <th class="border-r border-gray-300 last:border-r-0 bg-gray-800 text-white p-3 text-center text-sm font-medium">
                            {{ $day['name'] }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($timeSlots as $slot)
                    <tr class="border-b border-gray-200 last:border-b-0">
                        @foreach($weekDays as $day)
                        @php
                        $slotKey = $day['date'] . '-' . $slot['start'];
                        $isBooked = in_array($slotKey, $bookedSlots);
                        $isPreliminary = in_array($slotKey, $preliminaryBookedSlots);
                        $isSelected = in_array($slotKey, $selectedSlots);
                        @endphp
                        <td
                            class="border-r border-gray-200 last:border-r-0 p-2 text-center text-sm transition-all duration-200
                                            @if($isBooked)
                                                bg-red-100 text-red-800 cursor-not-allowed
                                            @elseif($isPreliminary)
                                                bg-blue-100 text-blue-800 cursor-not-allowed
                                            @elseif($isSelected)
                                                bg-green-100 text-green-800 cursor-pointer hover:bg-green-200
                                            @else
                                                cursor-pointer hover:bg-gray-50 text-gray-700
                                            @endif"
                            wire:click="toggleTimeSlot('{{ $slotKey }}')">
                            <div class="py-1">
                                {{ $slot['start'] }} - {{ $slot['end'] }}
                            </div>
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap gap-6 mb-6 items-center text-sm">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-100 border border-red-200 rounded"></div>
                <span class="font-medium">BOOKED</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-blue-100 border border-blue-200 rounded"></div>
                <span class="font-medium">PRELIMINARY BOOKED</span>
            </div>
            <div class="text-xs text-gray-600 italic ml-auto max-w-md">
                *For booking later than 6pm additional IDR 50k/hour will be charged for tennis court lights
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end">
            <button
                class="px-6 py-3 font-bold text-sm transition-all duration-200 rounded
                        @if(count($selectedSlots) === 0)
                            bg-gray-300 text-gray-500 cursor-not-allowed
                        @else
                            bg-gray-800 text-white hover:bg-gray-900 cursor-pointer
                        @endif"
                wire:click="confirmBooking"
                @disabled(count($selectedSlots)===0)>
                CONFIRM BOOKING(S)
            </button>
        </div>
    </div>
</div>
