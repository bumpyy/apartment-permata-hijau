

<div class="flex justify-end">
    <button wire:click="confirmBooking" @disabled(count($selectedSlots) === 0 || $quotaWarning) @class([
        'transform rounded-xl font-bold shadow-lg transition-all duration-500 hover:scale-105',
        'px-4 py-2 text-xs' => $compactView,
        'px-8 py-4 text-sm' => !$compactView,
        'bg-gray-300 text-gray-500 cursor-not-allowed' =>
            count($selectedSlots) === 0,
        'bg-orange-400 text-white cursor-not-allowed' => $quotaWarning,
        'bg-gradient-to-r from-gray-700 via-gray-800 to-gray-900 text-white hover:from-gray-800 hover:via-gray-900 hover:to-black cursor-pointer hover:shadow-xl' =>
            !$quotaWarning && count($selectedSlots) > 0,
    ])>
        @if ($quotaWarning)
            ⚠️ @if ($compactView)
                QUOTA
            @else
                QUOTA EXCEEDED
            @endif
        @else
            🎾 @if ($compactView)
                BOOK
            @else
                CONFIRM
            @endif
            @if (!$compactView)
                @if ($bookingType === 'mixed')
                    MIXED
                @else
                    {{ strtoupper($bookingType) }}
                @endif
                BOOKING(S)
            @endif
            @if (count($selectedSlots) > 0)
                ({{ count($selectedSlots) }})
            @endif
        @endif
    </button>
</div>
