<div class="mb-8 overflow-hidden rounded-xl border border-gray-300 shadow-lg">
    <div class="grid grid-cols-7 gap-0">
        <!-- Day headers -->
        @foreach (\Carbon\Carbon::getDays() as $dayName)
            <div class="bg-gray-100 p-2 text-center text-xs font-semibold text-gray-700">{{ $dayName }}</div>
        @endforeach

        <!-- Calendar days -->
        @foreach ($monthDays as $day)

            @if ($day['can_book_premium'])
                <a href="https://wa.me/{{ $whatsappNumber }}" target="_blank">
            @endif

            <div @class([
                'relative aspect-square border-b border-r border-gray-200 p-1 transition-all duration-200 ',
                'bg-purple-100/80 hover:bg-purple-50' => $day['can_book_premium'],
                'bg-blue-100/80 hover:bg-blue-50' => $day['can_book_free'],
                'bg-white hover:bg-gray-50' => !$day['is_bookable'],
            ])
                @if ($day['can_book_free'] && $day['is_current_month']) wire:click="showTimesForDate('{{ $day['date'] }}')" @endif>
                <div class="flex h-full flex-col">
                    <div class="flex items-start justify-between">
                        <div class="font-medium text-blue-600">
                            {{ $day['day_number'] }}
                        </div>

                        <!-- Booking indicators in top right -->
                        @if ($day['is_current_month'] && !$day['is_past'])
                            <div class="flex flex-col items-end gap-0.5">
                                @if ($day['selected_count'] > 0)
                                    <div class="h-2 w-2 rounded-full bg-green-500"
                                        title="{{ $day['selected_count'] }} selected"></div>
                                @endif
                                @if ($day['booked_count'] > 0)
                                    <div class="h-2 w-2 rounded-full bg-red-500"
                                        title="{{ $day['booked_count'] }} booked"></div>
                                @endif
                                @if ($day['pending_count'] > 0)
                                    <div class="h-2 w-2 rounded-full bg-yellow-500"
                                        title="{{ $day['pending_count'] }} pending"></div>
                                @endif
                            </div>
                        @endif
                    </div>

                    @if ($day['is_past'] && !$day['is_today'])
                        <div class="mt-1 text-gray-400">
                            Past
                        </div>
                    @elseif($day['is_bookable'] && $day['is_current_month'])
                        <div class="mt-1 flex-1 space-y-1">
                            <!-- Booking counts at bottom -->
                            @if ($day['booked_count'] > 0 || $day['pending_count'] > 0 || $day['selected_count'] > 0 || $day['available_count'] < 14)
                                <div class="mt-auto text-xs text-gray-600">
                                    {{-- @if ($day['available_count'] > 0)
                                        <span class="text-green-600">{{ $day['available_count'] }} free</span>
                                    @endif --}}
                                    @if ($day['booked_count'] > 0)
                                        <span class="text-red-600">{{ $day['booked_count'] }} booked</span>
                                    @endif
                                    @if ($day['pending_count'] > 0)
                                        <span class="text-yellow-600">{{ $day['pending_count'] }} pending</span>
                                    @endif
                                </div>
                            @endif

                            <div class="text-xs font-medium text-blue-600">Click to book</div>
                        </div>
                    @elseif($day['is_current_month'])
                        <div class="mt-1 text-gray-400">🔒</div>
                    @endif
                    @if ($day['is_today'])
                        <div>Today</div>
                    @endif
                </div>
            </div>

            @if ($day['can_book_premium'])
                </a>
            @endif
        @endforeach
    </div>
</div>
