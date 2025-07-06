

@if ($showLoginReminder)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div @class([
            'mx-4 w-full transform rounded-xl bg-white shadow-2xl',
            'max-w-sm p-4' => $compactView,
            'max-w-md p-6' => !$compactView,
        ])>
            <h3 @class([
                'mb-4 font-bold',
                'text-base' => $compactView,
                'text-lg' => !$compactView,
            ])>🔐 Login Required</h3>
            <p @class([
                'mb-6 text-gray-600',
                'text-sm' => $compactView,
                '' => !$compactView,
            ])>Please log in to your tenant account to proceed with booking.</p>
            <div class="flex justify-end gap-3">
                <button @class([
                    'text-gray-600 transition-colors hover:text-gray-800',
                    'px-3 py-1 text-sm' => $compactView,
                    'px-4 py-2' => !$compactView,
                ]) wire:click="closeModal">
                    Cancel
                </button>
                <button @class([
                    'rounded-lg bg-blue-600 text-white transition-colors hover:bg-blue-700',
                    'px-3 py-1 text-sm' => $compactView,
                    'px-4 py-2' => !$compactView,
                ]) wire:click="redirectToLogin">
                    🔑 Login
                </button>
            </div>
        </div>
    </div>
@endif
