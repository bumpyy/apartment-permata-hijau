<!-- Weekly Filter -->
<div class="mb-6 rounded-xl bg-white p-4 shadow">
    <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
        <div class="flex items-center gap-2">
            <button class="rounded bg-gray-100 px-3 py-2 text-gray-700 transition-colors hover:bg-gray-200"
                wire:click="prevWeek">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </button>
            <div class="min-w-[200px] text-center font-semibold text-gray-700">
                {{ $this->weeklyBookings['startOfWeek']->format('d M Y') }} -
                {{ $this->weeklyBookings['endOfWeek']->format('d M Y') }}
            </div>
            <button class="rounded bg-gray-100 px-3 py-2 text-gray-700 transition-colors hover:bg-gray-200"
                wire:click="nextWeek">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        </div>
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2">
                <input class="rounded border-gray-300" id="excludeCancelledWeekly" type="checkbox"
                    wire:model.live="excludeCancelled" />
                <label class="whitespace-nowrap text-sm text-gray-700" for="excludeCancelledWeekly">Exclude
                    Cancelled</label>
            </div>
            <input class="rounded border border-gray-200 p-2 text-sm focus:ring-2 focus:ring-blue-200" type="date"
                wire:model.live="weekPicker">
            <button
                class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700"
                wire:click="goToToday">
                Today
            </button>
        </div>
    </div>
</div>

<!-- Weekly List -->
<div class="cursor-grab overflow-x-auto rounded-xl bg-white p-4 shadow active:cursor-grabbing" x-data="{
    isDown: false,
    startX: 0,
    scrollLeft: 0
}"
    x-on:mousedown="isDown = true; startX = $event.pageX - $el.offsetLeft; scrollLeft = $el.scrollLeft; $el.classList.add('cursor-grabbing')"
    x-on:mouseleave="isDown = false; $el.classList.remove('cursor-grabbing')"
    x-on:mouseup="isDown = false; $el.classList.remove('cursor-grabbing')"
    x-on:mousemove="$event.preventDefault(); if (isDown) { const x = $event.pageX - $el.offsetLeft; const walk = (x - startX) * 2; $el.scrollLeft = scrollLeft - walk; }"
    x-on:selectstart="$event.preventDefault()">
    <div class="flex min-w-[900px] gap-4">
        @foreach ($this->weeklyBookings['days'] as $date => $slots)
            @php
                $dateObj = \Carbon\Carbon::parse($date);
                $isPast = $dateObj->isPast();
                $isToday = $dateObj->isToday();
            @endphp
            <div class="min-w-[180px] flex-1">
                <div @class([
                    'text-xs font-bold mb-2 text-center border-b pb-1',
                    'text-gray-500' => !$isPast,
                    'text-gray-400' => $isPast,
                ])>
                    {{ $dateObj->format('D, d M') }}
                    @if ($isPast)
                        <span class="ml-1 text-red-500">🔒</span>
                    @elseif($isToday)
                        <span class="ml-1 text-blue-500">📅</span>
                    @endif
                </div>
                <div class="flex min-h-[120px] flex-col gap-2">
                    @foreach ($this->weeklyBookings['timeSlots'] as $slotLabel)
                        <div class="mb-2">
                            <div class="mb-1 text-[11px] font-semibold text-gray-400">{{ $slotLabel['label'] }}</div>
                            @forelse($slots[$slotLabel['label']] as $booking)
                                <div wire:click="handleBookingCardClick({{ $booking->id }})"
                                    @class([
                                        'rounded-lg border p-2 shadow-sm mb-1 transition-colors',
                                        'bg-gray-50 hover:bg-gray-100 cursor-pointer' => true,
                                    ])>
                                    <div class="mb-1 flex items-center gap-2">
                                        <span
                                            class="inline-block rounded bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800">{{ $booking->court->name ?? '-' }}</span>
                                        <div class="text-xs font-semibold text-gray-800">{{ $booking->tenant->name }}
                                        </div>
                                        <span @class([
                                            'inline-block text-[10px] font-bold rounded px-2 py-0.5',
                                            'bg-green-100 text-green-800' => $booking->booking_type === 'free',
                                            'bg-purple-100 text-purple-800' => $booking->booking_type === 'premium',
                                        ])>
                                            @if ($booking->booking_type === 'free')
                                                🆓
                                            @else
                                                ⭐
                                            @endif
                                        </span>
                                    </div>
                                    <div class="mb-0.5 text-[11px] text-gray-500">
                                        {{ $booking->start_time instanceof \Carbon\Carbon ? $booking->start_time->format('H:i') : $booking->start_time }}
                                        -
                                        {{ $booking->end_time instanceof \Carbon\Carbon ? $booking->end_time->format('H:i') : $booking->end_time }}
                                    </div>
                                    <div class="mt-1">
                                        @php
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'confirmed' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                            ];
                                            $colorClass =
                                                $statusColors[$booking->status->value] ?? 'bg-gray-100 text-gray-800';
                                        @endphp
                                        <span @class([
                                            'inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                            $colorClass,
                                        ])>
                                            {{ ucfirst($booking->status->value) }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                @php
                                    $canBook = $this->canBookSlot($date, $slotLabel['start_time']);
                                    $isPast = !$canBook;
                                    $isToday = \Carbon\Carbon::parse($date)->isToday();
                                    $isPastTime =
                                        $isToday &&
                                        \Carbon\Carbon::createFromFormat('H:i', $slotLabel['start_time'])->isPast();
                                @endphp
                                <div @if ($canBook) wire:click="startAddBooking('{{ $date }}', '{{ $slotLabel['start_time'] }}', '{{ $slotLabel['end_time'] }}')" @endif
                                    @class([
                                        'rounded-lg border p-2 text-xs text-center mb-1 transition',
                                        'border-dashed bg-gray-50 text-blue-500 cursor-pointer hover:bg-blue-50' => $canBook,
                                        'border-gray-300 bg-gray-100 text-gray-400 cursor-not-allowed' => $isPast,
                                    ])
                                    title="{{ $canBook ? 'Add booking for this slot' : ($isPastTime ? 'Past time slot - cannot book' : 'Past date - cannot book') }}">
                                    @if ($canBook)
                                        + Add Booking
                                    @elseif($isPastTime)
                                        ⏰ Past Time
                                    @else
                                        🔒 Locked
                                    @endif
                                </div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Legend for Weekly View -->
<div class="mt-4 rounded-xl bg-white p-4 shadow">
    <h4 class="mb-2 text-sm font-semibold text-gray-700">📋 Booking Status Legend</h4>
    <div class="grid grid-cols-2 gap-3 text-xs md:grid-cols-4">
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 rounded border border-green-300 bg-green-100"></div>
            <span>🆓 Free Booking</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 rounded border border-purple-300 bg-purple-100"></div>
            <span>⭐ Premium Booking</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 rounded border border-gray-300 bg-gray-100"></div>
            <span>🔒 Past Date/Time</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 rounded border border-blue-300 bg-blue-100"></div>
            <span>📅 Today</span>
        </div>
    </div>
</div>
