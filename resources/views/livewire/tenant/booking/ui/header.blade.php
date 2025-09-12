<div class="relative overflow-hidden bg-gradient-to-r from-gray-600 to-gray-800 py-8 text-center text-white">

    <div class="relative z-10">
        <h1 class="text-3xl font-bold tracking-wide">🎾 TENNIS COURT {{ $this->courtNumber }} BOOKING</h1>
        <p class="mt-2 text-gray-200">Reserve your perfect playing time</p>

        <!-- Booking Status Indicators -->
        <div class="mt-4 flex flex-wrap justify-center gap-4 text-sm">
            <div class="flex items-center gap-2 rounded-full bg-green-600 px-3 py-1">
                <div class="h-2 w-2 rounded-full bg-green-300"></div>
                <span>🆓 Free Booking: Next Week</span>
            </div>
            @if ($isPremiumBookingOpen)
                <div class="flex items-center gap-2 rounded-full bg-purple-600 px-3 py-1">
                    <div class="h-2 w-2 rounded-full bg-purple-300"></div>
                    <span>⭐ Premium Booking: Open Today!</span>
                </div>
            @else
                <div class="flex items-center gap-2 rounded-full bg-gray-500 px-3 py-1">
                    <div class="h-2 w-2 rounded-full bg-gray-300"></div>
                    <span>⭐ Premium Opens: {{ $premiumBookingDate->format('M j, Y') }}</span>
                </div>
            @endif
        </div>
    </div>

</div>
