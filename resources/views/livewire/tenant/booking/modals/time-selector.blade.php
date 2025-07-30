@if ($showTimeSelector)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
        <div @class([
            'w-full transform rounded-xl bg-white shadow-2xl',
            'max-w-2xl',
        ])>
            <!-- Header -->
            <div class="rounded-t-xl border-b border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between">
                    <h3 @class(['font-bold text-gray-800', 'text-lg'])>
                        🕐 Select Time for
                        {{ \Carbon\Carbon::parse($selectedDateForTime)->format('l, F j, Y') }}
                    </h3>
                    <button class="text-gray-400 transition-colors hover:text-gray-600" wire:click="closeTimeSelector">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                @php $dayInfo = $this->getDateBookingInfo(\Carbon\Carbon::parse($selectedDateForTime)); @endphp
                <div class="mt-2 flex items-center gap-2">
                    @if ($dayInfo['can_book_free'])
                        <span @class([
                            'rounded-full bg-green-200 text-green-700',
                            'px-2 py-1 text-xs',
                        ])>🆓 Free Booking Available
                        </span>
                    @endif
                    @if ($dayInfo['can_book_premium'])
                        <span @class([
                            'rounded-full bg-purple-200 text-purple-700',
                            'px-2 py-1 text-xs',
                        ])>⭐ Premium Booking Available
                        </span>
                    @endif
                </div>
            </div>

            <!-- Time Slots Grid -->
            <div @class(['max-h-96 overflow-y-auto', 'p-4'])>
                <div @class(['grid gap-2', 'sm:grid-cols-2 lg:grid-cols-3'])>
                    @foreach ($availableTimesForDate as $timeSlot)
                        <div @class([
                            'rounded-lg border text-center transition-all duration-200',
                            'p-3',
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
                            @if ($timeSlot['is_available']) wire:click="toggleTimeSlot('{{ $timeSlot['slot_key'] }}')" @endif
                            title="{{ $timeSlot['is_booked'] ? 'Booked by another tenant' : ($timeSlot['is_past'] ? 'Past time slot' : 'Click to select') }}">
                            <div @class(['font-semibold', ''])>{{ $timeSlot['start_time'] }} -
                                {{ $timeSlot['end_time'] }}
                            </div>
                            @if ($timeSlot['is_past'])
                                <div class="text-xs">
                                    Past
                                </div>
                            @elseif($timeSlot['is_booked'])
                                <div class="text-xs">Booked</div>
                            @elseif($timeSlot['is_selected'])
                                <div class="text-xs">✓ Selected
                                </div>
                            @elseif($timeSlot['is_available'])
                                @if ($timeSlot['is_available'] && $timeSlot['slot_type'] === 'premium' && !$timeSlot['is_selected'])
                                    <a class="flex h-full w-full flex-col items-center justify-center text-xs opacity-60"
                                        href="https://wa.me/{{ $whatsappNumber }}" target="_blank">
                                        Chat to book
                                    </a>
                                @elseif ($timeSlot['is_available'] && $timeSlot['slot_type'] === 'free' && !$timeSlot['is_selected'])
                                    <div class="text-xs">🆓 Free</div>
                                @endif
                                @if ($timeSlot['is_peak'])
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
                    <div class="text-sm text-gray-600">
                        Click on available time slots to select them for booking
                    </div>
                    <button wire:click="closeTimeSelector" @class([
                        'bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors',
                        'px-4 py-2',
                    ])>
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
