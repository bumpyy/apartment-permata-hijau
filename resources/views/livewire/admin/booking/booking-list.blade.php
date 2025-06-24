<div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
    <h2 class="mb-6 text-lg font-semibold text-gray-900">
        Schedule for {{ $selectedDate }}
    </h2>

    <div class="space-y-3">
        <button wire:click="changeSlotDisplay('selected')" @class([
            'shadow-xs cursor-pointer rounded-md px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 ',
            'bg-blue-600 text-white' => $slotDisplay === 'selected',
            'hover:bg-gray-50' => $slotDisplay !== 'selected',
        ]) type="button">
            Selected date
        </button>

        <button wire:click="changeSlotDisplay('all')" @class([
            'shadow-xs cursor-pointer rounded-md px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 ',
            'bg-blue-600 text-white' => $slotDisplay === 'all',
            'hover:bg-gray-50' => $slotDisplay !== 'all',
        ]) type="button">
            All slots
        </button>

        @if ($slotDisplay === 'selected')
            @forelse ($sortedBookings as $booking)
                {{-- @dd($booking->status === 'pending') --}}
                <x-booking-card :booking="$booking" />
            @empty
                <div class="py-12 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                        <flux:icon.clock class="h-8 w-8 text-gray-400" />
                    </div>
                    <p class="text-sm text-gray-500">No bookings scheduled for this date</p>
                </div>
            @endforelse
        @else
            <div>
                @forelse ($timeSlots as $slot)
                    @if ($sortedBookings->contains('start_time', $slot->start_time))
                        <x-booking-card :booking="$sortedBookings->firstWhere('start_time', $slot->start_time)" />
                    @else
                        <x-booking-card :booking="$slot" />
                    @endif
                @empty
                    <div class="py-12 text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                            <flux:icon.clock class="h-8 w-8 text-gray-400" />
                        </div>
                        <p class="text-sm text-gray-500">No bookings scheduled for this date</p>
                    </div>
                @endforelse
            </div>
        @endif
    </div>

</div>
