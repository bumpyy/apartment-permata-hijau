<div class="booking-card bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-lg p-6 hover:shadow-md transition-all duration-300">
    <div class="flex items-center flex-wrap gap-2 justify-between">
        <div class="flex flex-wrap items-center space-x-4">
            <div class="flex-shrink-0">
                <div class="py-2 px-3 bg-gradient-to-br from-blue-500 to-purple-600 rounded-md flex items-center justify-center text-white font-bold">
                    {{ $booking->court->name }}
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <h3 class="text-lg font-semibold text-gray-900">
                    Court {{ $booking->court->name }}
                    <span @class([
                        'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium sm:ml-2',
                        'bg-blue-100 text-blue-800' => $booking->booking_type === 'free',
                        'bg-purple-100 text-purple-800' => $booking->booking_type !== 'free',
                    ])>
                        @if($booking->booking_type === 'free') 🆓 Free @else ⭐ Premium @endif
                    </span>
                </h3>
                <p class="text-sm text-gray-600">
                    📅 {{ $booking->date->format('l, F j, Y') }} •
                    🕐 {{ $booking->start_time->format('g:i A') }} - {{ $booking->end_time->format('g:i A') }}
                </p>
                @if($booking->is_light_required)
                <p class="text-xs text-orange-600 mt-1">💡 Court lights included (+IDR 50k)</p>
                @endif
                @if($booking->booking_reference)
                <p class="text-xs text-gray-500 mt-1">Reference: #{{ $booking->booking_reference }}</p>
                @endif

                <!-- Cancellation Status Message -->
                @if(isset($isPast) && $isPast)
                    @if($booking->status === \App\Enum\BookingStatusEnum::CANCELLED && $booking->cancellation_reason)
                        <div class="mt-2 p-2 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-xs text-red-700">
                                <svg class="inline w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                Cancelled: {{ $booking->cancellation_reason }}
                            </p>
                        </div>
                    @endif
                @else
                    @if(!$this->canCancelBooking($booking))
                        <div class="mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded-md">
                            <p class="text-xs text-yellow-700">
                                <svg class="inline w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                                {{ $this->getCancellationMessage($booking) }}
                            </p>
                        </div>
                    @endif
                @endif
            </div>
        </div>
        <div class="flex items-center flex-wrap gap-3">
            <span
                @class([
                    'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium',
                    'bg-green-100 text-green-800'=> $booking->status === \App\Enum\BookingStatusEnum::CONFIRMED,
                    'bg-orange-100 text-orange-800' => $booking->status === \App\Enum\BookingStatusEnum::PENDING,
                    'bg-red-100 text-red-800' => $booking->status === \App\Enum\BookingStatusEnum::CANCELLED,
                ])
                >
                @if($booking->status === \App\Enum\BookingStatusEnum::CONFIRMED) ✅ Confirmed
                @elseif($booking->status === \App\Enum\BookingStatusEnum::PENDING) ⏳ Pending
                @else ❌ Cancelled @endif
            </span>
            @if(!isset($isPast) && $this->canCancelBooking($booking))
            <button
                wire:click="openCancelModal({{ $booking->id }})"
                class="px-3 py-1 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors text-sm">
                ❌ Cancel
            </button>
            @elseif(!isset($isPast) && $this->getSiteSettings()->allow_booking_cancellations)
            <button
                disabled
                class="px-3 py-1 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed text-sm">
                ❌ Cancel
            </button>
            @endif
        </div>
    </div>
</div>
