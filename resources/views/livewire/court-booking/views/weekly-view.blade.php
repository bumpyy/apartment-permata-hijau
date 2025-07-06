<div class="mb-8 overflow-x-auto rounded-xl border border-gray-300 shadow-lg">
    <table class="w-full border-collapse bg-white">
        <thead>
            <tr>
                <th @class([
                    'border-r sticky z-10 left-0 border-gray-300 bg-gray-100 text-left font-semibold text-gray-700',
                    'p-2 text-xs' => $compactView,
                    'p-4' => !$compactView,
                ])>Time</th>
                @foreach ($weekDays as $day)
                    <th @class([
                        'border-r border-gray-300 last:border-r-0 text-white text-center relative',
                        'p-1' => $compactView,
                        'p-4' => !$compactView,
                        'bg-gradient-to-b from-blue-500 to-blue-600' => $day['is_today'],
                        'bg-gradient-to-b from-gray-400 to-gray-500' =>
                            $day['is_past'] && !$day['is_today'],
                        'bg-gradient-to-b from-green-600 to-green-700' =>
                            $day['can_book_free'] && !$day['is_today'] && !$day['is_past'],
                        'bg-gradient-to-b from-purple-600 to-purple-700' =>
                            $day['can_book_premium'] &&
                            !$day['is_today'] &&
                            !$day['can_book_free'] &&
                            !$day['is_past'],
                        'bg-gradient-to-b from-gray-300 to-gray-400' =>
                            !$day['is_bookable'] && !$day['is_today'] && !$day['is_past'],
                    ])>
                        <div class="flex flex-col items-center">
                            <div @class([
                                'font-bold',
                                'text-xs' => $compactView,
                                'text-sm' => !$compactView,
                            ])>
                                {{ $compactView ? substr($day['day_name'], 0, 1) : $day['day_name'] }}</div>
                            <div @class([
                                'font-bold',
                                'text-sm' => $compactView,
                                'text-2xl' => !$compactView,
                            ])>{{ $day['day_number'] }}</div>
                            @if (!$compactView)
                                <div class="text-xs opacity-90">{{ $day['month_name'] }}</div>
                            @endif

                            @if ($day['is_today'])
                                <div @class([
                                    'mt-1 rounded-full bg-blue-400 px-1 py-0.5 text-xs font-bold',
                                    'text-xs' => $compactView,
                                ])>{{ $compactView ? '●' : 'TODAY' }}</div>
                            @elseif($day['is_past'])
                                <div @class([
                                    'mt-1 rounded-full bg-gray-300 px-1 py-0.5 text-xs',
                                    'text-xs' => $compactView,
                                ])>{{ $compactView ? '✕' : 'PAST' }}</div>
                            @elseif($day['can_book_free'])
                                <div @class([
                                    'mt-1 rounded-full bg-green-400 px-1 py-0.5 text-xs font-bold',
                                    'text-xs' => $compactView,
                                ])>{{ $compactView ? 'F' : '🆓 FREE' }}</div>
                            @elseif($day['can_book_premium'])
                                <div @class([
                                    'mt-1 rounded-full bg-purple-400 px-1 py-0.5 text-xs font-bold',
                                    'text-xs' => $compactView,
                                ])>{{ $compactView ? 'P' : '⭐ PREMIUM' }}</div>
                            @else
                                <div @class([
                                    'mt-1 rounded-full bg-gray-300 px-1 py-0.5 text-xs',
                                    'text-xs' => $compactView,
                                ])>{{ $compactView ? '🔒' : 'LOCKED' }}</div>
                            @endif
                        </div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($timeSlots as $slot)
                <tr class="border-b border-gray-200 transition-colors duration-200 last:border-b-0 hover:bg-gray-50">
                    <td @class([
                        'border-r z-10 sticky left-0 border-gray-300 bg-gray-50 font-medium text-gray-700',
                        'p-2' => $compactView,
                        'p-4' => !$compactView,
                    ])>
                        <div @class(['text-xs' => $compactView, 'text-sm' => !$compactView])>{{ $slot['start'] }}</div>
                        @if (!$compactView)
                            <div class="text-xs text-gray-500">{{ $slot['end'] }}</div>
                        @endif
                    </td>
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
                            $canBook = $day['is_bookable'] && !$isPastSlot && !$isBooked && !$isPreliminary;

                            // $showBookingInfo = ($isPastSlot || !$day['is_bookable']) && ($isBooked || $isPreliminary);
                            $showBookingInfo = false;
                        @endphp
                        <td @class([
                            'time-slot text-center transition-all duration-200',
                            'p-1' => $compactView,
                            'p-3' => !$compactView,
                            'bg-gray-100 text-gray-400' =>
                                ($isPastSlot || !$day['is_bookable']) &&
                                !$showBookingInfo &&
                                !$isBooked,
                            'bg-blue-100 text-blue-800 cursor-pointer border-l-4 border-blue-400' => $isBooked,
                            'bg-yellow-100 text-yellow-800 cursor-pointer border-l-4 border-yellow-400' => $isPreliminary,
                            'bg-green-100 text-green-800 border-l-4 border-green-500 transform scale-95 shadow-inner' =>
                                $isSelected && $slotType === 'free',
                            'bg-purple-100 text-purple-800 border-l-4 border-purple-500 transform scale-95 shadow-inner' =>
                                $isSelected && $slotType === 'premium',
                            'hover:bg-green-50 hover:shadow-md transform hover:scale-105 cursor-pointer' =>
                                $canBook && $slotType === 'free' && !$isSelected,
                            'hover:bg-purple-50 hover:shadow-md transform hover:scale-105 cursor-pointer' =>
                                $canBook && $slotType === 'premium' && !$isSelected,
                        ])
                            @if ($canBook) wire:click="toggleTimeSlot('{{ $slotKey }}')" @endif
                            @if ($showBookingInfo) title="@if ($isBooked)Booked by: {{ $bookedSlot['tenant_name'] ?? 'Unknown' }}
                            @else Pending booking @endif"
                        @else
                            title="@if ($isPastSlot) Past slot @elseif(!$day['is_bookable']) Not available for booking @else {{ $day['formatted_date'] ?? $day['date'] }} {{ $slot['start'] }}-{{ $slot['end'] }} ({{ ucfirst($slotType) }}) @endif"
                            @endif>

                            @if ($isPastSlot && !$showBookingInfo)
                                <div class="text-xs text-gray-400">-</div>
                            @elseif(!$day['is_bookable'] && !$isPastSlot && !$showBookingInfo)
                                <div class="text-xs text-gray-400">🔒</div>
                            @elseif($isSelected)
                                <div @class([
                                    'font-bold flex items-center justify-center',
                                    'text-xs' => $compactView || !$compactView,
                                    'text-green-700' => $slotType === 'free',
                                    'text-purple-700' => $slotType === 'premium',
                                ])>
                                    @if ($compactView)
                                        <div class="flex flex-col items-center">
                                            <div class="text-lg">✓</div>
                                            <div class="text-xs">{{ $slotType === 'free' ? 'F' : 'P' }}</div>
                                        </div>
                                    @else
                                        ✓ Selected
                                    @endif
                                </div>
                            @elseif($isBooked)
                                <div @class([
                                    'font-bold text-blue-700 flex items-center justify-center',
                                    'text-xs' => $compactView || !$compactView,
                                ])>
                                    @if ($compactView)
                                        <div class="flex flex-col items-center">
                                            <div class="text-lg">●</div>
                                            <div class="text-xs">
                                                {{ $bookedSlot['is_own_booking'] ?? false ? 'YOU' : 'BKD' }}</div>
                                        </div>
                                    @else
                                        @if ($bookedSlot['is_own_booking'] ?? false)
                                            Your Booking
                                        @else
                                            Booked
                                        @endif
                                    @endif
                                </div>
                            @elseif($isPreliminary)
                                <div @class([
                                    'font-bold text-yellow-700 flex items-center justify-center',
                                    'text-xs' => $compactView || !$compactView,
                                ])>
                                    @if ($compactView)
                                        <div class="flex flex-col items-center">
                                            <div class="text-lg">⏳</div>
                                            <div class="text-xs">
                                                {{ $preliminarySlot['is_own_booking'] ?? false ? 'YOU' : 'PND' }}
                                            </div>
                                        </div>
                                    @else
                                        @if ($preliminarySlot['is_own_booking'] ?? false)
                                            Your Pending
                                        @else
                                            Pending
                                        @endif
                                    @endif
                                </div>
                            @elseif($canBook)
                                <div @class([
                                    'opacity-60 flex items-center justify-center',
                                    'text-xs' => $compactView || !$compactView,
                                ])>
                                    @if ($compactView)
                                        <div class="flex flex-col items-center">
                                            <div class="text-sm">{{ $slotType === 'free' ? '🆓' : '⭐' }}</div>
                                            <div class="text-xs">{{ $slotType === 'free' ? 'F' : 'P' }}</div>
                                        </div>
                                    @else
                                        @if ($slotType === 'free')
                                            🆓 Free
                                        @else
                                            ⭐ Premium
                                        @endif
                                    @endif
                                </div>
                            @endif

                            @if ($slot['is_peak'] && $canBook && !$compactView)
                                <div class="mt-1 text-xs text-orange-600">💡</div>
                            @endif

                            @if ($canBook && !$compactView)
                                @php
                                    $slotDateTime = \Carbon\Carbon::createFromFormat(
                                        'Y-m-d H:i',
                                        $day['date'] . ' ' . $slot['start'],
                                    );
                                    $endTime = $slotDateTime->copy()->addHour()->format('H:i');
                                    $crossCourtConflicts = $this->checkCrossCourtConflicts($day['date'], $slot['start'], $endTime);
                                @endphp
                                @if (!empty($crossCourtConflicts))
                                    <div class="mt-1 text-xs text-red-600" title="Cross-court conflict: You have bookings on other courts at this time">⚠️</div>
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
