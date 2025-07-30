<div class="mb-8 rounded-xl border border-gray-300 bg-white shadow-lg">
    <div @class(['border-b border-gray-200 bg-gray-50', 'p-4 rounded-t-xl'])>
        <div class="flex items-center justify-between">
            <h3 @class(['font-semibold text-gray-800', 'text-lg'])>
                {{ $currentDate->format('l, F j, Y') }}
            </h3>
            <div class="flex items-center gap-2">
                @php $dayInfo = $this->getDateBookingInfo($currentDate); @endphp
                @if ($currentDate->isPast())
                    <span @class([
                        'rounded-full bg-gray-200 text-gray-600',
                        'px-2 py-1 text-xs',
                    ])>Past Date
                    </span>
                @elseif($dayInfo['can_book_free'])
                    <span @class([
                        'rounded-full bg-green-200 text-green-700',
                        'px-2 py-1 text-xs',
                    ])>🆓 Free Booking
                    </span>
                @elseif($dayInfo['can_book_premium'])
                    <span @class([
                        'rounded-full bg-purple-200 text-purple-700',
                        'px-2 py-1 text-xs',
                    ])>⭐ Premium Booking
                    </span>
                @else
                    <span @class([
                        'rounded-full bg-gray-200 text-gray-600',
                        'px-2 py-1 text-xs',
                    ])>🔒 Locked
                    </span>
                @endif
            </div>
        </div>
    </div>
    <div @class(['grid gap-2', 'p-4 sm:grid-cols-2 lg:grid-cols-3'])>

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
                'p-4',
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
                <div @class(['font-semibold', ''])>{{ $slot['start'] }} - {{ $slot['end'] }}
                </div>
                @if ($isPastSlot && !$isBooked && !$isPreliminary)
                    <div class="text-xs">
                        Past
                    </div>
                @elseif($isBooked)
                    <div class="text-xs">
                        @if ($bookedSlot['is_own_booking'] ?? false)
                            Your Booking
                        @else
                            Booked
                        @endif
                    </div>
                @elseif($isPreliminary)
                    <div class="text-xs">
                        @if ($preliminarySlot['is_own_booking'] ?? false)
                            Your Pending
                        @else
                            Pending
                        @endif
                    </div>
                @elseif($isSelected)
                    <div class="text-xs">✓ Selected
                    </div>
                @elseif($canBook)
                    @if ($canBook && $slotType === 'premium' && !$isSelected)
                        <a class="flex h-full w-full flex-col items-center justify-center text-xs opacity-60"
                            href="https://wa.me/{{ $whatsappNumber }}" target="_blank">
                            Chat to book
                        </a>
                    @elseif($canBook && $slotType === 'free' && !$isSelected)
                        <div class="text-xs">🆓 Free</div>
                    @endif
                    @if ($slot['is_peak'])
                        <div class="text-xs text-orange-600">💡 Lights required</div>
                    @endif

                    @if (true)
                        @php
                            $slotDateTime = \Carbon\Carbon::createFromFormat(
                                'Y-m-d H:i',
                                $currentDate->format('Y-m-d') . ' ' . $slot['start'],
                            );
                            $endTime = $slotDateTime->copy()->addHour()->format('H:i');
                            $crossCourtConflicts = $this->checkCrossCourtConflicts(
                                $currentDate->format('Y-m-d'),
                                $slot['start'],
                                $endTime,
                            );
                        @endphp
                        @if (!empty($crossCourtConflicts))
                            <div class="text-xs text-red-600"
                                title="Cross-court conflict: You have bookings on other courts at this time">⚠️ Conflict
                            </div>
                        @endif
                    @endif
                @else
                    <div class="text-xs text-gray-400">🔒 Locked
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
