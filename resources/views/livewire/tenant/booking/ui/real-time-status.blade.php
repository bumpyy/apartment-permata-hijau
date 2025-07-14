<div @class([
    'mb-4 rounded-lg border border-green-200 bg-gradient-to-r from-green-50 to-blue-50',
    'p-3',
])>
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <div class="flex h-6 w-6 items-center justify-center rounded-full bg-green-100">
                <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <div @class([
                    'font-medium text-green-800',
                    'text-sm',
                ])>🛡️ Real-time Duplicate Prevention Active</div>
                <div @class([
                    'text-green-600',
                    'text-xs',
                ])>Preventing multiple bookings for the same slot</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @if ($isRefreshing)
                <div class="flex items-center gap-1">
                    <div class="h-2 w-2 animate-spin rounded-full border-2 border-green-600 border-t-transparent"></div>
                    <span @class([
                        'text-green-600',
                        'text-xs',
                    ])>Updating...</span>
                </div>
            @else
                <div class="flex items-center gap-1">
                    @if ($this->isPollingEnabled())
                        <div class="h-2 w-2 rounded-full bg-green-600"></div>
                        <span @class([
                            'text-green-600',
                            'text-xs',
                        ])>Live</span>
                    @else
                        <div class="h-2 w-2 rounded-full bg-gray-400"></div>
                        <span @class([
                            'text-gray-500',
                            'text-xs',
                        ])>Manual</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
