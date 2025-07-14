@if ($showConfirmModal)
    <div class="animate-fade-in fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="animate-scale-in mx-4 w-full max-w-lg transform rounded-xl bg-white p-6 shadow-2xl">
            <h3 class="mb-6 text-xl font-bold">
                @if ($bookingType === 'mixed')
                    🎾 Mixed Booking Confirmation
                @else
                    🎾 {{ ucfirst($bookingType) }} Booking Confirmation
                @endif
            </h3>

            <div class="mb-6 space-y-4">
                @foreach ($pendingBookingData as $booking)
                    <div class="rounded-lg border bg-gray-50 p-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-semibold">{{ $booking['date']->format('l, F j, Y') }}</div>
                                <div class="text-lg">{{ $booking['start_time'] . ' - ' . $booking['end_time'] }}
                                </div>
                                @if ($booking['is_light_required'])
                                    <div class="mt-1 text-sm text-orange-600">
                                        💡 additional IDR 50k/hour for tennis court lights
                                    </div>
                                @endif
                            </div>
                            <span @class([
                                'bg-blue-100 text-blue-800' => $booking['booking_type'] === 'free',
                                'bg-purple-100 text-purple-800' => $booking['booking_type'] !== 'free',
                                'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium',
                            ])
                                @if ($booking['booking_type'] === 'free') 🆓
                        @else
                        ⭐ @endif
                                {{ strtoupper($booking['booking_type']) }} </span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-gray-600">
                <p>💳 *Please process the payment to the Receptionist before using the tennis court</p>
                <p>⚠️ *Please be responsible with your bookings. Failure to comply may result in being blacklisted.
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <button class="rounded-lg px-6 py-2 text-gray-600 transition-colors hover:text-gray-800"
                    wire:click="closeModal">
                    Cancel
                </button>
                <button
                    class="transform rounded-lg bg-gradient-to-r from-gray-700 to-gray-900 px-6 py-2 text-white transition-all duration-300 hover:scale-105 hover:from-gray-800 hover:to-black"
                    wire:click="processBooking">
                    🎾 CONFIRM BOOKING(S)
                </button>
            </div>
        </div>
    </div>
@endif
