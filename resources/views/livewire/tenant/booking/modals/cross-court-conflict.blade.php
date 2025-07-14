@if ($showCrossCourtConflictModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="mx-4 w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-100">
                        <span class="text-2xl">🎾</span>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Cross-Court Booking Conflict</h3>
                        <p class="text-sm text-gray-600">You already have bookings at this time</p>
                    </div>
                </div>
                <button class="text-gray-400 hover:text-gray-600" wire:click="closeCrossCourtConflictModal">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                        </path>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="mb-6">
                <div class="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-orange-800">Booking Conflict Detected</h4>
                            <div class="mt-2 text-sm text-orange-700">
                                <p>You cannot book multiple courts at the same time slot.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    <h4 class="font-medium text-gray-900">Your Existing Bookings:</h4>
                    @foreach ($crossCourtConflictDetails as $conflict)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100">
                                        <span
                                            class="text-sm font-semibold text-blue-600">{{ substr($conflict['court_name'], -1) }}</span>
                                    </div>
                                    <div>
                                        <div class="font-medium text-gray-900">{{ $conflict['court_name'] }}</div>
                                        <div class="text-sm text-gray-600">{{ $conflict['start_time'] }} -
                                            {{ $conflict['end_time'] }}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-xs text-gray-500">Ref: #{{ $conflict['booking_reference'] }}</div>
                                    <div class="text-xs">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                            'bg-green-100 text-green-800' => $conflict['status'] === 'confirmed',
                                            'bg-yellow-100 text-yellow-800' => $conflict['status'] === 'pending',
                                        ])>
                                            {{ ucfirst($conflict['status']) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end space-x-3">
                <button
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    wire:click="closeCrossCourtConflictModal">
                    Close
                </button>
            </div>
        </div>
    </div>
@endif
