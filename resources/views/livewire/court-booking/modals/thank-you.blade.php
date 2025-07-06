

@if ($showThankYouModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div @class([
            'mx-4 w-full transform rounded-xl bg-white text-center shadow-2xl',
            'max-w-sm p-6' => $compactView,
            'max-w-md p-8' => !$compactView,
        ])>
            <div @class([
                'mb-4',
                'text-4xl' => $compactView,
                'text-6xl' => !$compactView,
            ])>🎾</div>
            <h3 @class([
                'mb-4 font-bold',
                'text-lg' => $compactView,
                'text-xl' => !$compactView,
            ])>Thank you for your booking!</h3>
            <div @class([
                'mb-6 rounded-lg bg-gray-100 font-bold text-gray-800',
                'py-2 text-xl' => $compactView,
                'py-4 text-3xl' => !$compactView,
            ])>
                #{{ $bookingReference }}
            </div>
            <button @class([
                'transform rounded-lg bg-gradient-to-r from-gray-600 to-gray-800 text-white transition-all duration-300 hover:scale-105 hover:from-gray-700 hover:to-gray-900',
                'px-6 py-2 text-sm' => $compactView,
                'px-8 py-3' => !$compactView,
            ]) wire:click="closeModal">
                🏠 @if ($compactView)
                    BACK
                @else
                    BACK TO BOOKING
                @endif
            </button>
        </div>
    </div>
@endif
