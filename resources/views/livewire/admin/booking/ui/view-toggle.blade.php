<div class="mb-4 flex items-center justify-between">
    <div class="flex gap-2">
        <button
            class="{{ $viewMode === 'table' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }} rounded px-4 py-2 font-medium focus:outline-none"
            wire:click="setViewMode('table')">Table View</button>
        <button
            class="{{ $viewMode === 'weekly' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-700' }} rounded px-4 py-2 font-medium focus:outline-none"
            wire:click="setViewMode('weekly')">Booking View</button>
    </div>

    <!-- Export Button -->
    <button
        class="inline-flex items-center rounded-md border border-transparent bg-purple-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-purple-700 focus:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 active:bg-purple-900"
        wire:click="openExportModal">
        <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
            </path>
        </svg>
        Export Report
    </button>
</div>
