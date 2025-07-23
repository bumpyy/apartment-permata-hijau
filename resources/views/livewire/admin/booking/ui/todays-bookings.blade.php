@if ($this->todaysBookings->isNotEmpty())
    <div class="mb-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="flex items-center text-xl font-bold text-gray-900">
                <svg class="mr-2 h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z">
                    </path>
                </svg>
                Today's Bookings ({{ \Carbon\Carbon::today()->format('l, d M Y') }})
            </h2>
            <button class="text-sm text-blue-600 hover:text-blue-800"
                wire:click="$set('showTodaysBookings', !$showTodaysBookings)">
                {{ $showTodaysBookings ? 'Hide' : 'Show' }}
            </button>
        </div>

        @if ($showTodaysBookings)
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->todaysBookings as $courtName => $bookings)
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-4 py-3">
                            <h3 class="text-lg font-semibold text-white">Court {{ $courtName }}</h3>
                            <p class="text-sm text-blue-100">{{ count($bookings) }} booking(s) today</p>
                        </div>
                        <div class="space-y-3 p-4">
                            @foreach ($bookings as $booking)
                                <div class="rounded-lg border-l-4 border-blue-500 bg-gray-50 p-3">
                                    <div class="mb-2 flex items-center justify-between">
                                        <div class="font-semibold text-gray-900">{{ $booking->tenant->name }}</div>
                                        <span @class([
                                            'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                                            'bg-green-100 text-green-800' =>
                                                $booking->status === \App\Enum\BookingStatusEnum::CONFIRMED,
                                            'bg-yellow-100 text-yellow-800' =>
                                                $booking->status === \App\Enum\BookingStatusEnum::PENDING,
                                        ])>
                                            {{ ucfirst($booking->status->value) }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        <div class="mb-1 flex items-center">
                                            <svg class="mr-1 h-4 w-4 text-gray-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ $booking->start_time->format('g:i A') }} -
                                            {{ $booking->end_time->format('g:i A') }}
                                        </div>
                                        @if ($booking->is_light_required)
                                            <div class="flex items-center text-xs text-orange-600">
                                                <svg class="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path
                                                        d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM15.657 5.757a1 1 0 00-1.414-1.414l-.707.707a1 1 0 001.414 1.414l.707-.707zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5.05 6.464A1 1 0 106.464 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zM5 10a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zM8 16v-1h4v1a2 2 0 11-4 0zM12 14c.015-.34.208-.646.477-.859a4 4 0 10-4.954 0c.27.213.462.519.476.859h4.002z">
                                                    </path>
                                                </svg>
                                                Lights required
                                            </div>
                                        @endif
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <span class="text-xs text-gray-500">#{{ $booking->booking_reference }}</span>
                                        <button class="text-xs text-blue-600 hover:text-blue-800"
                                            wire:click="showDetail({{ $booking->id }})">
                                            View Details
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
