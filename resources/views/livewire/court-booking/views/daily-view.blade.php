<div class="mb-8 rounded-xl border border-gray-300 bg-white shadow-lg">
    <div @class([
        'border-b border-gray-200 bg-gray-50',
        'p-2 rounded-t-xl' => $compactView,
        'p-4 rounded-t-xl' => !$compactView,
    ])>
        <div class="flex items-center justify-between">
            <h3 @class([
                'font-semibold text-gray-800',
                'text-sm' => $compactView,
                'text-lg' => !$compactView,
            ])>
                {{ $currentDate->format($compactView ? 'M j, Y' : 'l, F j, Y') }}
            </h3>
            <div class="flex items-center gap-2">
                @php $dayInfo = $this->getDateBookingInfo($currentDate); @endphp
                @if ($currentDate->isPast())
                    <span @class([
                        'rounded-full bg-gray-200 text-gray-600',
                        'px-2 py-1 text-xs' => !$compactView,
                        'px-1 text-xs' => $compactView,
                    ])>Past @if (!$compactView)
                            Date
                        @endif
                    </span>
                @elseif($dayInfo['can_book_free'])
                    <span @class([
                        'rounded-full bg-green-200 text-green-700',
                        'px-2 py-1 text-xs' => !$compactView,
                        'px-1 text-xs' => $compactView,
                    ])>🆓 @if (!$compactView)
                            Free Booking
                        @endif
                    </span>
                @elseif($dayInfo['can_book_premium'])
                    <span @class([
                        'rounded-full bg-purple-200 text-purple-700',
                        'px-2 py-1 text-xs' => !$compactView,
                        'px-1 text-xs' => $compactView,
                    ])>⭐ @if (!$compactView)
                            Premium Booking
                        @endif
                    </span>
                @else
                    <span @class([
                        'rounded-full bg-gray-200 text-gray-600',
                        'px-2 py-1 text-xs' => !$compactView,
                        'px-1 text-xs' => $compactView,
                    ])>🔒 @if (!$compactView)
                            Locked
                        @endif
                    </span>
                @endif
            </div>
        </div>
    </div>
    <div @class([
        'grid gap-2',
        'p-2 grid-cols-4' => $compactView,
        'p-4 sm:grid-cols-2 lg:grid-cols-3' => !$compactView,
    ])>

        @foreach ($timeSlots as $slot)
            @php
                $slotKey = $currentDate->format('Y-m-d') . '-' . $slot['start'];
                $slotType = $this->getSlotType($slotKey);
                $isSelected = in_array($slotKey, $selectedSlots);

                $bookedSlot = collect($bookedSlots)->firstWhere('key', $slotKey);
                $preliminarySlot = collect($preliminaryBookedSlots)->firstWhere('key', $slotKey);
                $isBooked = $bookedSlot !== null;
                $isPreliminary = $preliminarySlot !== null;

                $slotDateTime = \Carbon\Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $currentDate->format('Y-m-d') . ' ' . $slot['start'],
                );
                $isPastSlot = $slotDateTime->isPast();
                $canBook = $this->canBookSlot($currentDate) && !$isPastSlot && !$isBooked && !$isPreliminary;
            @endphp
            <div @class([
                'rounded-lg border text-center transition-all duration-200',
                'p-2' => $compactView,
                'p-4' => !$compactView,
                'bg-gray-100 text-gray-400' => $isPastSlot && !$isBooked && !$isPreliminary,
                'bg-red-100 text-red-800 border-red-300' => $isBooked,
                'bg-yellow-100 text-yellow-800 border-yellow-300' => $isPreliminary,
                'bg-green-100 text-green-800 border-green-300 cursor-pointer hover:bg-green-200' =>
                    $canBook && $slotType === 'free' && !$isSelected,
                'bg-purple-100 text-purple-800 border-purple-300 cursor-pointer hover:bg-purple-200' =>
                    $canBook && $slotType === 'premium' && !$isSelected,
                'bg-green-200 text-green-900 border-green-400 shadow-inner' =>
                    $isSelected && $slotType === 'free',
                'bg-purple-200 text-purple-900 border-purple-400 shadow-inner' =>
                    $isSelected && $slotType === 'premium',
            ])
                @if ($canBook) wire:click="toggleTimeSlot('{{ $slotKey }}')" @endif>
                <div @class([
                    'font-semibold',
                    'text-xs' => $compactView,
                    '' => !$compactView,
                ])>{{ $slot['start'] }}@if (!$compactView)
                        - {{ $slot['end'] }}
                    @endif
                </div>
                @if ($isPastSlot && !$isBooked && !$isPreliminary)
                    <div class="text-xs">
                        @if ($compactView)
                            -
                        @else
                            Past
                        @endif
                    </div>
                @elseif($isBooked)
                    <div class="text-xs">
                        @if ($bookedSlot['is_own_booking'] ?? false)
                            @if ($compactView)
                                Yours
                            @else
                                Your Booking
                            @endif
                        @else
                            Booked
                        @endif
                    </div>
                @elseif($isPreliminary)
                    <div class="text-xs">
                        @if ($preliminarySlot['is_own_booking'] ?? false)
                            @if ($compactView)
                                Pending
                            @else
                                Your Pending
                            @endif
                        @else
                            Pending
                        @endif
                    </div>
                @elseif($isSelected)
                    <div class="text-xs">✓ @if (!$compactView)
                            Selected
                        @endif
                    </div>
                @elseif($canBook)
                    <div class="text-xs">{{ $slotType === 'free' ? '🆓' : '⭐' }} @if (!$compactView)
                            {{ $slotType === 'free' ? ' Free' : ' Premium' }}
                        @endif
                    </div>
                    @if ($slot['is_peak'] && !$compactView)
                        <div class="text-xs text-orange-600">💡 Lights required</div>
                    @endif

                    @if (!$compactView)
                        @php
                            $slotDateTime = \Carbon\Carbon::createFromFormat(
                                'Y-m-d H:i',
                                $currentDate->format('Y-m-d') . ' ' . $slot['start'],
                            );
                            $endTime = $slotDateTime->copy()->addHour()->format('H:i');
                            $crossCourtConflicts = $this->checkCrossCourtConflicts($currentDate->format('Y-m-d'), $slot['start'], $endTime);
                        @endphp
                        @if (!empty($crossCourtConflicts))
                            <div class="text-xs text-red-600" title="Cross-court conflict: You have bookings on other courts at this time">⚠️ Conflict</div>
                        @endif
                    @endif
                @else
                    <div class="text-xs text-gray-400">🔒 @if (!$compactView)
                            Locked
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
