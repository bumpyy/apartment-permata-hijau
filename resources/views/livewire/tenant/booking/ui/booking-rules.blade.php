<div class="mb-6 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-purple-50 p-4">
    <h3 class="mb-2 font-bold text-gray-800">📋 Booking Rules</h3>
    <div class="grid gap-2 text-sm md:grid-cols-2">
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 rounded-full bg-green-500"></div>
            <span><strong>Free Booking:</strong> Next week only
                ({{ \Carbon\Carbon::today()->addWeek()->startOfWeek()->format('M j') }} -
                {{ \Carbon\Carbon::today()->addWeek()->endOfWeek()->format('M j') }})</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-3 w-3 rounded-full bg-purple-500"></div>
            <span><strong>Premium Booking:</strong> Beyond next week @if ($isPremiumBookingOpen)
                    (Open Now!)
                @else
                    (Opens {{ $premiumBookingDate->format('M j') }})
                @endif
            </span>
        </div>
    </div>
    @php
        $siteSettings = app(\App\Settings\SiteSettings::class);
        $whatsappNumber = preg_replace('/[^0-9]/', '', $siteSettings->whatsapp_number);
    @endphp
    <div class="mt-2 text-xs text-purple-700">
        ⭐ To book premium slots, please <a href="https://wa.me/{{ $whatsappNumber }}" target="_blank" class="underline text-purple-800 hover:text-purple-900">chat admin via WhatsApp</a>.
    </div>
</div>
