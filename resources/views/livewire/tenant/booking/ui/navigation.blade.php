<div class="mb-6 flex flex-col items-center justify-center gap-4 sm:flex-row">
    <div class="inline-flex rounded-lg border border-gray-300 bg-white p-1 shadow-sm">
        <button
            class="{{ $viewMode === 'monthly' ? 'bg-blue-500 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100' }} rounded-md px-4 py-2 text-sm font-medium transition-all duration-200"
            wire:click="switchView('monthly')">
            📆 Monthly
        </button>
        <button
            class="{{ $viewMode === 'weekly' ? 'bg-blue-500 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100' }} rounded-md px-4 py-2 text-sm font-medium transition-all duration-200"
            wire:click="switchView('weekly')">
            📅 Weekly
        </button>
        {{-- <button wire:click="switchView('daily')" class="rounded-md px-4 py-2 text-sm font-medium transition-all duration-200 {{ $viewMode === 'daily' ? 'bg-blue-500 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-100' }}">
            🕐 Daily
        </button> --}}
    </div>
</div>

<!-- Navigation Controls -->
<div
    class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 p-4 shadow-sm">
    <button
        class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-700 shadow-sm transition-all duration-300 hover:bg-gray-50"
        wire:click="previousPeriod">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Previous
    </button>

    <div class="flex items-center gap-2">
        <div class="text-center">
            @if ($viewMode === 'weekly')
                <h3 class="text-lg font-semibold">
                    {{ $currentWeekStart->format('M j') }} -
                    {{ $currentWeekStart->copy()->addDays(6)->format('M j, Y') }}
                </h3>
            @elseif($viewMode === 'monthly')
                <h3 class="text-lg font-semibold">{{ $currentMonthStart->format('F Y') }}</h3>
            @else
                <h3 class="text-lg font-semibold">{{ $currentDate->format('l, F j, Y') }}</h3>
            @endif
        </div>
        <!-- Date Picker Button -->
        <button
            class="ml-2 rounded-lg bg-purple-100 px-3 py-1 text-purple-700 transition-all duration-300 hover:bg-purple-200"
            wire:click="openDatePicker">
            📅 Jump to Date
        </button>
    </div>

    <div class="flex items-center gap-2">
        <button
            class="{{ !$isRefreshing ? 'bg-green-100 text-green-700 hover:bg-green-200 cursor-pointer' : 'bg-gray-100 text-gray-400 cursor-not-allowed' }} rounded-lg px-3 py-2 transition-all duration-300"
            wire:click="manualRefresh" @disabled($isRefreshing) title="Refresh booking data">
            @if ($isRefreshing)
                ⏳ Refreshing...
            @else
                🔄 Refresh
            @endif
        </button>
        @if ($lastRefreshTime)
            <span class="text-xs text-gray-500">Last: {{ $lastRefreshTime }}</span>
        @endif
        <button class="rounded-lg bg-blue-100 px-4 py-2 text-blue-700 transition-all duration-300 hover:bg-blue-200"
            wire:click="goToToday">
            📅 Today
        </button>
        <button
            class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-700 shadow-sm transition-all duration-300 hover:bg-gray-50"
            wire:click="nextPeriod">
            Next
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
    </div>
</div>
