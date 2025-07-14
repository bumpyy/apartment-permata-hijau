@if (count($selectedSlots) > 0)
    <div @class([
        'mb-8 rounded-xl border border-green-200 bg-gradient-to-r from-green-50 to-blue-50 shadow-sm',
        'p-6',
    ])>
        <h4 @class([
            'mb-4 flex items-center gap-2 font-bold text-gray-800',
            '',
        ])>
            🎯 Selected Time Slots ({{ count($selectedSlots) }})
            @if ($bookingType === 'mixed')
                <span @class([
                    'rounded-full bg-gradient-to-r from-blue-500 to-purple-500 text-white',
                    'px-2 py-1 text-xs',
                ])>
                    Mixed Booking
                </span>
            @endif
        </h4>
        <div @class([
            'flex flex-wrap',
            'gap-3',
        ])>
            @foreach ($selectedSlots as $slot)
                @php
                    $parts = explode('-', $slot);
                    if (count($parts) >= 4) {
                        $date = \Carbon\Carbon::createFromFormat(
                            'Y-m-d',
                            $parts[0] . '-' . $parts[1] . '-' . $parts[2],
                        );
                        $time = count($parts) == 4 ? $parts[3] : $parts[3] . ':' . $parts[4];
                        $slotType = $this->getSlotType($slot);
                    }
                @endphp
                @if (isset($date) && isset($time))
                    <span @class([
                        'inline-flex items-center rounded-full font-medium transition-all duration-300 hover:scale-105',
                        'px-4 py-2 text-sm',
                        'bg-gradient-to-r from-green-100 to-green-200 text-green-800 border border-green-300' =>
                            $slotType === 'free',
                        'bg-gradient-to-r from-purple-100 to-purple-200 text-purple-800 border border-purple-300' =>
                            $slotType !== 'free',
                    ])>
                        @if ($slotType === 'free')
                            🆓
                        @else
                            ⭐
                        @endif
                        {{ $date->format('M j') }} at {{ $time }}
                        <button @class([
                            'ml-2 transition-transform duration-200 hover:scale-110',
                            'text-green-600 hover:text-green-800' => $slotType === 'free',
                            'text-purple-600 hover:text-purple-800' => $slotType !== 'free',
                        ])
                            wire:click="toggleTimeSlot('{{ $slot }}')">
                            ✕
                        </button>
                    </span>
                @endif
            @endforeach
        </div>
    </div>
@endif
