@if ($showCancelModal && $bookingToCancel)
    <div class="fixed inset-0 z-50 flex h-full w-full items-center justify-center overflow-y-auto bg-gray-600 bg-opacity-50"
        id="cancelModal">
        <div class="relative top-20 mx-auto w-full max-w-lg rounded-md border bg-white p-5 shadow-lg">
            <div class="mt-3">
                <!-- Header -->
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-gray-900">Cancel Booking</h3>
                    <button class="text-gray-400 hover:text-gray-600" wire:click="closeCancelModal">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                <!-- Booking Details -->
                <div class="mb-4 rounded-lg bg-gray-50 p-4">
                    <div class="mb-3 flex items-center space-x-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 font-bold text-white">
                            {{ $bookingToCancel->court->name }}
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900">Court {{ $bookingToCancel->court->name }}</h4>
                            <p class="text-sm text-gray-600">
                                {{ $bookingToCancel->date->format('l, F j, Y') }}
                            </p>
                            <p class="text-sm text-gray-600">
                                {{ $bookingToCancel->start_time->format('g:i A') }} -
                                {{ $bookingToCancel->end_time->format('g:i A') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <span @class([
                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                            'bg-blue-100 text-blue-800' => $bookingToCancel->booking_type === 'free',
                            'bg-purple-100 text-purple-800' =>
                                $bookingToCancel->booking_type !== 'free',
                        ])
                            @if ($bookingToCancel->booking_type === 'free') 🆓 Free
                        @else
                            ⭐ Premium @endif
                            </span>
                            <span @class([
                                'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                'bg-green-100 text-green-800' =>
                                    $bookingToCancel->status === \App\Enum\BookingStatusEnum::CONFIRMED,
                                'bg-orange-100 text-orange-800' =>
                                    $bookingToCancel->status !== \App\Enum\BookingStatusEnum::CONFIRMED,
                            ])
                                @if ($bookingToCancel->status === \App\Enum\BookingStatusEnum::CONFIRMED) ✅ Confirmed
                        @else
                            ⏳ Pending @endif
                                </span>
                    </div>

                    <div class="mt-3">
                        <p class="text-sm text-gray-600">
                            <strong>Tenant:</strong> {{ $bookingToCancel->tenant->name }}
                        </p>
                        <p class="text-sm text-gray-600">
                            <strong>Reference:</strong> #{{ $bookingToCancel->booking_reference }}
                        </p>
                    </div>
                </div>

                <!-- Warning Message -->
                <div class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-3">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-yellow-800">Important</h4>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>• This action cannot be undone</p>
                                <p>• The tenant's quota will be restored</p>
                                <p>• The court will be available for other bookings</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cancellation Reason (Optional) -->
                <div class="mb-4">
                    <label class="mb-2 block text-sm font-medium text-gray-700" for="cancellation_reason">
                        Cancellation Reason (Optional)
                    </label>
                    <textarea
                        class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-blue-500"
                        id="cancellation_reason" wire:model="cancellationReason" rows="3"
                        placeholder="Please provide a reason for cancellation (optional)..."></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3">
                    <button class="rounded-md bg-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-400"
                        wire:click="closeCancelModal">
                        Keep Booking
                    </button>
                    <button class="rounded-md bg-red-600 px-4 py-2 text-white transition-colors hover:bg-red-700"
                        wire:click="confirmCancellation">
                        Confirm Cancellation
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
