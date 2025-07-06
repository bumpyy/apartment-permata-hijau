

@if ($quotaWarning)
    <div @class([
        'mb-6 rounded-r-lg border-l-4 border-orange-400 bg-orange-50',
        'p-3' => $compactView,
        'p-4' => !$compactView,
    ])>
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-orange-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <p @class([
                    'text-orange-700',
                    'text-xs' => $compactView,
                    'text-sm' => !$compactView,
                ])>⚠️ {{ $quotaWarning }}</p>
            </div>
        </div>
    </div>
@endif
