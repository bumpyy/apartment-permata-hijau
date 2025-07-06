

<div class="mb-6 flex flex-col items-center justify-center gap-4 sm:flex-row">
    <div class="inline-flex rounded-lg border border-gray-300 bg-white p-1 shadow-sm">
        <button wire:click="switchView('monthly')" @class([
            'rounded-md px-4 py-2 text-sm font-medium transition-all duration-200',
            'bg-blue-500 text-white shadow-sm' => $viewMode === 'monthly',
            'text-gray-700 hover:bg-gray-100' => $viewMode !== 'monthly',
        ])>
            📆 Monthly
        </button>

        <button wire:click="switchView('weekly')" @class([
            'rounded-md px-4 py-2 text-sm font-medium transition-all duration-200',
            'bg-blue-500 text-white shadow-sm' => $viewMode === 'weekly',
            'text-gray-700 hover:bg-gray-100' => $viewMode !== 'weekly',
        ])>
            📅 Weekly
        </button>

        <button wire:click="switchView('daily')" @class([
            'rounded-md px-4 py-2 text-sm font-medium transition-all duration-200',
            'bg-blue-500 text-white shadow-sm' => $viewMode === 'daily',
            'text-gray-700 hover:bg-gray-100' => $viewMode !== 'daily',
        ])>
            🕐 Daily
        </button>
    </div>

    <!-- Compact View Toggle -->
    <button wire:click="toggleCompactView" @class([
        'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-200',
        'bg-gray-600 text-white' => $compactView,
        'bg-gray-100 text-gray-700 hover:bg-gray-200' => !$compactView,
    ])>
        @if ($compactView)
            📱 Compact
        @else
            🖥️ Full
        @endif
    </button>
</div>

<!-- Navigation Controls -->
<div @class([
    'mb-6 flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-gradient-to-r from-gray-50 to-gray-100 shadow-sm',
    'p-2' => $compactView,
    'p-4' => !$compactView,
])>
    <button wire:click="previousPeriod" @class([
        'flex items-center gap-2 rounded-lg transition-all duration-300',
        'px-2 py-1 text-sm' => $compactView,
        'px-4 py-2' => !$compactView,
        'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 cursor-pointer shadow-sm',
    ])>
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        @if (!$compactView)
            Previous
        @endif
    </button>

    <div class="flex items-center gap-2">
        <div class="text-center">
            @if ($viewMode === 'weekly')
                <h3 @class([
                    'font-semibold',
                    'text-sm' => $compactView,
                    'text-lg' => !$compactView,
                ])>
                    {{ $currentWeekStart->format('M j') }} -
                    {{ $currentWeekStart->copy()->addDays(6)->format('M j, Y') }}
                </h3>
            @elseif($viewMode === 'monthly')
                <h3 @class([
                    'font-semibold',
                    'text-sm' => $compactView,
                    'text-lg' => !$compactView,
                ])>{{ $currentMonthStart->format('F Y') }}</h3>
            @else
                <h3 @class([
                    'font-semibold',
                    'text-sm' => $compactView,
                    'text-lg' => !$compactView,
                ])>{{ $currentDate->format('l, F j, Y') }}</h3>
            @endif
        </div>

        <!-- Date Picker Button -->
        <button wire:click="openDatePicker" @class([
            'rounded-lg bg-purple-100 text-purple-700 transition-all duration-300 hover:bg-purple-200',
            'px-2 py-1 text-xs' => $compactView,
            'px-3 py-1 ml-2' => !$compactView,
        ])>
            📅 @if (!$compactView)
                Jump to Date
            @endif
        </button>
    </div>

    <div class="flex items-center gap-2">
        <button wire:click="manualRefresh" @disabled($isRefreshing) @class([
            'rounded-lg transition-all duration-300',
            'px-2 py-1 text-xs' => $compactView,
            'px-3 py-2' => !$compactView,
            'bg-green-100 text-green-700 hover:bg-green-200 cursor-pointer' => !$isRefreshing,
            'bg-gray-100 text-gray-400 cursor-not-allowed' => $isRefreshing,
        ]) title="Refresh booking data">
            @if ($isRefreshing)
                ⏳
            @else
                🔄
            @endif
            @if (!$compactView)
                @if ($isRefreshing)
                    Refreshing...
                @else
                    Refresh
                @endif
            @endif
        </button>
        @if ($lastRefreshTime && !$compactView)
            <span class="text-xs text-gray-500">Last: {{ $lastRefreshTime }}</span>
        @endif

        <button wire:click="goToToday" @class([
            'rounded-lg bg-blue-100 text-blue-700 transition-all duration-300 hover:bg-blue-200',
            'px-2 py-1 text-xs' => $compactView,
            'px-4 py-2' => !$compactView,
        ])>
            📅 @if (!$compactView)
                Today
            @endif
        </button>

        <button wire:click="nextPeriod" @class([
            'flex items-center gap-2 rounded-lg transition-all duration-300',
            'px-2 py-1 text-sm' => $compactView,
            'px-4 py-2' => !$compactView,
            'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 cursor-pointer shadow-sm',
        ])>
            @if (!$compactView)
                Next
            @endif
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
    </div>
</div>
