<div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
    <h2 class="mb-6 text-lg font-semibold text-gray-900">
        Schedule for {{ $selectedDate }}
    </h2>

    <div class="space-y-3">
        @forelse ($bookings->sortBy('start_time') as $booking)
            {{-- @dd($booking->status === 'pending') --}}
            <div class="group relative rounded-xl bg-gray-50 p-4 transition-all duration-200 hover:bg-gray-100 hover:shadow-md"
                key="{{ $booking->id }}">
                <div class="flex items-center gap-4">
                    {{-- <img class="h-12 w-12 rounded-full border-2 border-white object-cover shadow-sm"
                         src="tenantName{{ $booking->tenant->avatar }}" alt="{{ $booking->tenant->name }}" /> --}}

                    <div class="flex-1">
                        <div class="mb-1 flex items-center gap-2">
                            <h3 class="font-medium text-gray-900">{{ $booking->tenant->name }}</h3>
                            <span class="flex items-center gap-1" class="rounded-full px-2 py-1 text-xs font-medium"
                                @class([
                                    'bg-emerald-50 text-emerald-700 border-emerald-200' =>
                                        $booking->status === 'confirmed',
                                    'bg-amber-50 text-amber-700 border-amber-200' =>
                                        $booking->status === 'pending',
                                    'bg-red-50 text-red-700 border-red-200' => $booking->status === 'cancelled',
                                    'bg-gray-50 text-gray-700 border-gray-200' => $booking->status === 'none',
                                ])>
                                {{ $booking->status }} {{ $booking->booking_type }}
                            </span>
                        </div>
                        <p class="mb-1 text-sm text-gray-600">
                            {{ $booking->start_time->format('H:i') }} - {{ $booking->end_time->format('H:i') }}
                        </p>
                        @if ($booking->notes)
                            <p class="mt-1 text-sm text-gray-500">{{ $booking->notes }}</p>
                        @endif
                    </div>

                    @if ($booking->date->gt(\Carbon\Carbon::today()))
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button
                                class="rounded-lg p-2 text-gray-400 opacity-0 transition-colors duration-200 hover:bg-white hover:text-gray-600 group-hover:opacity-100"
                                @click="open = !open">
                                <flux:icon.chevron-down class="h-5 w-5" />
                            </button>

                            <div class="absolute right-0 top-full z-10 mt-2 w-48 rounded-lg border border-gray-200 bg-white py-2 shadow-lg"
                                id="menu-{{ $booking->id }}" x-show="open">
                                <button
                                    class="flex w-full items-center gap-3 px-4 py-2 text-sm text-gray-700 transition-colors duration-150 hover:bg-gray-50"
                                    class="h-4 w-4" @click="open = false">
                                    <flux:icon.pencil />
                                    Edit Booking
                                </button>
                                @if ($booking->status === 'pending')
                                    <button
                                        class="flex w-full items-center gap-3 px-4 py-2 text-sm text-emerald-700 transition-colors duration-150 hover:bg-emerald-50"
                                        @click="open = false">
                                        <flux:icon.check class="h-4 w-4" />
                                        Confirm Booking
                                    </button>
                                @endif

                                <button
                                    class="flex w-full items-center gap-3 px-4 py-2 text-sm text-red-700 transition-colors duration-150 hover:bg-red-50"
                                    @click="open = false" wire:click="$parent.cancelBooking({{ $booking->id }})">
                                    <flux:icon.trash class="h-4 w-4" />
                                    Cancel Booking
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="py-12 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                    <Clock class="h-8 w-8 text-gray-400" />
                </div>
                <p class="text-sm text-gray-500">No bookings scheduled for this date</p>
            </div>
        @endforelse
    </div>

</div>
