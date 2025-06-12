<div class="mb-8 overflow-hidden rounded-xl border border-gray-300 shadow-lg">
    <div class="grid grid-cols-7 gap-0">
        <!-- Day headers -->
        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
            <div @class([
                'bg-gray-100 text-center font-semibold text-gray-700',
                'p-2 text-xs' => $compactView,
                'p-4' => !$compactView,
            ])>{{ $compactView ? substr($dayName, 0, 1) : $dayName }}</div>
        @endforeach

        <!-- Calendar days -->
        @foreach ($monthDays as $day)
            <div @class([
                'border-r border-b border-gray-200 transition-all duration-200 relative',
                'aspect-square p-1' => $compactView,
                'aspect-square p-2' => !$compactView,
                'bg-white hover:bg-gray-50' => $day['is_current_month'] && !$day['is_past'],
                'bg-gray-50 text-gray-400' => !$day['is_current_month'] || $day['is_past'],
                'bg-blue-100 border-blue-300' => $day['is_today'],
                'cursor-pointer hover:shadow-md' =>
                    $day['is_bookable'] && $day['is_current_month'],
            ])
                @if ($day['is_bookable'] && $day['is_current_month']) wire:click="showTimesForDate('{{ $day['date'] }}')" @endif>
                <div class="flex h-full flex-col">
                    <div class="flex items-start justify-between">
                        <div @class([
                            'font-medium',
                            'text-xs' => $compactView,
                            'text-sm' => !$compactView,
                            'text-blue-600 font-bold' => $day['is_today'],
                            'text-gray-900' =>
                                $day['is_current_month'] && !$day['is_today'] && !$day['is_past'],
                            'text-gray-400' => !$day['is_current_month'] || $day['is_past'],
                        ])>
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

                    @if ($day['is_past'])
                        <div @class([
                            'mt-1 text-gray-400',
                            'text-xs' => $compactView || !$compactView,
                        ])>
                            @if ($compactView)
                                -
                            @else
                                Past
                            @endif
                        </div>
                    @elseif($day['is_bookable'] && $day['is_current_month'])
                        <div class="mt-1 flex-1 space-y-1">
                            @if ($day['can_book_free'])
                                <div @class([
                                    'rounded bg-green-100 text-green-700',
                                    'px-1 py-0.5 text-xs' => !$compactView,
                                    'text-xs' => $compactView,
                                ])>🆓 @if (!$compactView)
                                        Free
                                    @endif
                                </div>
                            @endif
                            @if ($day['can_book_premium'])
                                <div @class([
                                    'rounded bg-purple-100 text-purple-700',
                                    'px-1 py-0.5 text-xs' => !$compactView,
                                    'text-xs' => $compactView,
                                ])>⭐ @if (!$compactView)
                                        Premium
                                    @endif
                                </div>
                            @endif

                            <!-- Booking counts at bottom -->
                            @if (
                                !$compactView &&
                                    ($day['booked_count'] > 0 ||
                                        $day['pending_count'] > 0 ||
                                        $day['selected_count'] > 0 ||
                                        $day['available_count'] < 14))
                                <div class="mt-auto text-xs text-gray-600">
                                    @if ($day['available_count'] > 0)
                                        <span class="text-green-600">{{ $day['available_count'] }} free</span>
                                    @endif
                                    @if ($day['booked_count'] > 0)
                                        <span class="text-red-600">{{ $day['booked_count'] }} booked</span>
                                    @endif
                                    @if ($day['pending_count'] > 0)
                                        <span class="text-yellow-600">{{ $day['pending_count'] }} pending</span>
                                    @endif
                                </div>
                            @endif

                            @if (!$compactView)
                                <div class="text-xs font-medium text-blue-600">Click to book</div>
                            @endif
                        </div>
                    @elseif($day['is_current_month'])
                        <div @class([
                            'mt-1 text-gray-400',
                            'text-xs' => $compactView || !$compactView,
                        ])>🔒 @if (!$compactView)
                                Locked
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
