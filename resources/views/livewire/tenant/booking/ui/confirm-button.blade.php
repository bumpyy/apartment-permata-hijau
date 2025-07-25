<div class="flex justify-end">

    @if ($bookingType === 'premium' && count($selectedSlots) > 0)
        <a class="block transform cursor-pointer rounded-xl bg-green-500 px-8 py-4 text-center text-sm font-bold text-white shadow-lg transition-all duration-500 hover:scale-105 hover:bg-green-600 hover:shadow-xl"
            href="https://wa.me/{{ $whatsappNumber }}" target="_blank">
            ⭐ Chat Admin to Book Premium Slot(s)
        </a>
    @else
        <button wire:click="confirmBooking" @disabled(count($selectedSlots) === 0 || $quotaWarning) @class([
            'transform rounded-xl font-bold shadow-lg transition-all duration-500 hover:scale-105',
            'px-8 py-4 text-sm',
            'bg-gray-300 text-gray-500 cursor-not-allowed' =>
                count($selectedSlots) === 0,
            'bg-orange-400 text-white cursor-not-allowed' => $quotaWarning,
            'bg-gradient-to-r from-gray-700 via-gray-800 to-gray-900 text-white hover:from-gray-800 hover:via-gray-900 hover:to-black cursor-pointer hover:shadow-xl' =>
                !$quotaWarning && count($selectedSlots) > 0,
        ])>
            @if ($quotaWarning)
                ⚠️ QUOTA EXCEEDED
            @else
                🎾 CONFIRM
                @if ($bookingType === 'mixed')
                    MIXED
                @else
                    {{ strtoupper($bookingType) }}
                @endif
                BOOKING(S)
                @if (count($selectedSlots) > 0)
                    ({{ count($selectedSlots) }})
                @endif
            @endif
        </button>
    @endif
</div>
