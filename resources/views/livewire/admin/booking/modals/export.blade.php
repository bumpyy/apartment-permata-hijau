@if ($showExportModal)
    <div class="fixed inset-0 z-50 flex h-full w-full items-center justify-center overflow-y-auto bg-gray-600 bg-opacity-50"
     id="exportModal">
        <div class="relative top-20 mx-auto w-full max-w-2xl rounded-md border bg-white p-5 shadow-lg">
            <div class="mt-3">
                <!-- Header -->
                <div class="mb-6 flex items-center justify-between">
                    <h3 class="text-xl font-bold text-gray-900">📊 Export Bookings Report</h3>
                    <button class="text-gray-400 hover:text-gray-600" wire:click="closeExportModal">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </button>
                </div>

                <!-- Export Form -->
                <form class="space-y-6" wire:submit.prevent="exportBookings">
                    <!-- Date Range -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Date From</label>
                            <input
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                type="date" wire:model="exportDateFrom" required>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Date To</label>
                            <input
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                type="date" wire:model="exportDateTo" required>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Status</label>
                            <select
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                wire:model="exportStatusFilter">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Court</label>
                            <select
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                wire:model="exportCourtFilter">
                                <option value="">All Courts</option>
                                @foreach ($this->courts as $court)
                                    <option value="{{ $court->id }}">{{ $court->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Booking Type</label>
                            <select
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                wire:model="exportBookingTypeFilter">
                                <option value="">All Types</option>
                                <option value="free">Free</option>
                                <option value="premium">Premium</option>
                            </select>
                        </div>
                    </div>

                    <!-- Export Format -->
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Export Format</label>
                        <div class="flex gap-4">
                            <label class="flex items-center">
                                <input class="mr-2" type="radio" wire:model="exportFormat" value="excel">
                                <span class="text-sm">📊 Excel (.xlsx)</span>
                            </label>
                            <label class="flex items-center">
                                <input class="mr-2" type="radio" wire:model="exportFormat" value="pdf">
                                <span class="text-sm">📄 PDF (.pdf)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-3 pt-4">
                        <button
                            class="rounded-md bg-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-400"
                            type="button" wire:click="closeExportModal">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled" @class([
                            'px-4 py-2 rounded-md transition-colors',
                            'bg-purple-600 text-white hover:bg-purple-700 cursor-pointer' => !$isExporting,
                            'bg-gray-400 text-gray-600 cursor-not-allowed' => $isExporting,
                        ])
                            @if ($isExporting) disabled @endif>
                            @if ($isExporting)
                                <div class="flex items-center">
                                    <div
                                        class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent">
                                    </div>
                                    Exporting...
                                </div>
                            @else
                                📥 Export Report
                            @endif
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
