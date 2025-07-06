

@if ($compactView)
    <div class="mb-4 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 p-3 text-xs">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1">
                <span class="font-bold text-green-700">F</span>
                <span>Free</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="font-bold text-purple-700">P</span>
                <span>Premium</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="text-lg">✓</span>
                <span>Selected</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="text-lg">●</span>
                <span>Booked</span>
            </div>
            <div class="flex items-center gap-1">
                <span class="text-lg">⏳</span>
                <span>Pending</span>
            </div>
            <div class="flex items-center gap-1">
                <span>🔒</span>
                <span>Locked</span>
            </div>
        </div>
    </div>
@else
    <div class="mb-8 flex flex-wrap items-center gap-6 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 p-6 text-sm">
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded border-l-4 border-red-400 bg-red-100"></div>
            <span class="font-medium">Booked</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded border-l-4 border-yellow-400 bg-yellow-100"></div>
            <span class="font-medium">Pending</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded border-l-4 border-green-500 bg-green-100"></div>
            <span class="font-medium">🆓 Free Selected</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded border-l-4 border-purple-500 bg-purple-100"></div>
            <span class="font-medium">⭐ Premium Selected</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-4 w-4 rounded bg-gray-100"></div>
            <span class="font-medium">🔒 Locked/Past</span>
        </div>
        <div class="ml-auto max-w-md text-xs italic text-gray-600">
            *💡 After 6pm additional charges apply for court lights
        </div>
    </div>
@endif
