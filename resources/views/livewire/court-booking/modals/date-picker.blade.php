@if ($showDatePicker)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
        <div @class([
            'w-full transform rounded-xl bg-white shadow-2xl',
            'max-w-lg' => $compactView,
            'max-w-2xl' => !$compactView,
        ])>
            <!-- Header -->
            <div class="rounded-t-xl border-b border-gray-200 bg-gray-50 p-4">
                <h3 @class([
                    'font-bold text-gray-800',
                    'text-sm' => $compactView,
                    'text-lg' => !$compactView,
                ])>📅 Jump to Date</h3>

                <!-- Date Picker Mode Selector -->
                <div class="mt-3 flex gap-1 rounded-lg border bg-white p-1">
                    <button wire:click="setDatePickerMode('day')" @class([
                        'flex-1 rounded-md font-medium transition-all duration-200',
                        'px-2 py-1 text-xs' => $compactView,
                        'px-3 py-2 text-sm' => !$compactView,
                        'bg-blue-500 text-white' => $datePickerMode === 'day',
                        'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'day',
                    ])>
                        📅 Day
                    </button>
                    <button wire:click="setDatePickerMode('week')" @class([
                        'flex-1 rounded-md font-medium transition-all duration-200',
                        'px-2 py-1 text-xs' => $compactView,
                        'px-3 py-2 text-sm' => !$compactView,
                        'bg-blue-500 text-white' => $datePickerMode === 'week',
                        'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'week',
                    ])>
                        📅 Week
                    </button>
                    <button wire:click="setDatePickerMode('month')" @class([
                        'flex-1 rounded-md font-medium transition-all duration-200',
                        'px-2 py-1 text-xs' => $compactView,
                        'px-3 py-2 text-sm' => !$compactView,
                        'bg-blue-500 text-white' => $datePickerMode === 'month',
                        'text-gray-700 hover:bg-gray-100' => $datePickerMode !== 'month',
                    ])>
                        📅 Month
                    </button>
                </div>
            </div>

            <!-- Month/Year Selectors -->
            <div class="border-b border-gray-200 p-4">
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label @class([
                            'block font-medium text-gray-700 mb-1',
                            'text-xs' => $compactView,
                            'text-sm' => !$compactView,
                        ])>Month</label>
                        <select wire:model.live="selectedMonth" @class([
                            'w-full rounded-lg border border-gray-300 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500',
                            'px-2 py-1 text-xs' => $compactView,
                            'px-3 py-2 text-sm' => !$compactView,
                        ])>
                            @foreach ($availableMonths as $value => $name)
                                <option value="{{ $value }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label @class([
                            'block font-medium text-gray-700 mb-1',
                            'text-xs' => $compactView,
                            'text-sm' => !$compactView,
                        ])>Year</label>
                        <select wire:model.live="selectedYear" @class([
                            'w-full rounded-lg border border-gray-300 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500',
                            'px-2 py-1 text-xs' => $compactView,
                            'px-3 py-2 text-sm' => !$compactView,
                        ])>
                            @foreach ($availableYears as $year)
                                <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <!-- Calendar Content -->
            <div @class([
                'max-h-96 overflow-y-auto',
                'p-2' => $compactView,
                'p-4' => !$compactView,
            ])>
                @if ($datePickerMode === 'day')
                    <!-- Day Picker -->
                    <div class="mb-2 grid grid-cols-7 gap-1">
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName)
                            <div @class([
                                'text-center font-medium text-gray-500',
                                'p-1 text-xs' => $compactView,
                                'p-2 text-xs' => !$compactView,
                            ])>
                                {{ $compactView ? substr($dayName, 0, 1) : $dayName }}
                            </div>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-7 gap-1">
                        @foreach ($calendarDays as $day)
                            <button wire:click="selectDate('{{ $day['date'] }}')" @class([
                                'aspect-square rounded-lg transition-all duration-200 hover:scale-105',
                                'p-1 text-xs' => $compactView,
                                'p-2 text-sm' => !$compactView,
                                'text-gray-900 hover:bg-blue-50 border border-transparent hover:border-blue-200' =>
                                    $day['is_current_month'] &&
                                    !$day['is_past'] &&
                                    !$day['is_today'] &&
                                    $day['booking_type'] === 'none',
                                'text-gray-400 bg-gray-50' => !$day['is_current_month'] || $day['is_past'],
                                'bg-blue-500 text-white font-bold' => $day['is_today'],
                                'bg-green-100 text-green-800 border border-green-300 hover:bg-green-200' =>
                                    $day['can_book_free'] && !$day['is_today'],
                                'bg-purple-100 text-purple-800 border border-purple-300 hover:bg-purple-200' =>
                                    $day['can_book_premium'] && !$day['is_today'] && !$day['can_book_free'],
                                'cursor-pointer' => $day['is_current_month'],
                                'cursor-not-allowed' => !$day['is_current_month'],
                            ])
                                @disabled(!$day['is_current_month'])
                                title="{{ $day['formatted_date'] }} - {{ $day['booking_type'] === 'free' ? 'Free Booking' : ($day['booking_type'] === 'premium' ? 'Premium Booking' : 'Not Available') }}">
                                <div class="font-medium">{{ $day['day_number'] }}</div>
                                @if ($day['can_book_free'])
                                    <div class="text-xs">🆓</div>
                                @elseif($day['can_book_premium'])
                                    <div class="text-xs">⭐</div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                @elseif($datePickerMode === 'week')
                    <!-- Week Picker -->
                    <div class="space-y-2">
                        @foreach ($calendarWeeks as $week)
                            <button wire:click="selectWeek('{{ $week['week_start'] }}')"
                                @class([
                                    'w-full rounded-lg border text-left transition-all duration-200 hover:scale-105',
                                    'p-2' => $compactView,
                                    'p-3' => !$compactView,
                                    'bg-blue-100 border-blue-300 text-blue-800' => $week['is_current_week'],
                                    'bg-gray-100 border-gray-300 text-gray-500' => $week['is_past_week'],
                                    'bg-green-100 border-green-300 text-green-800 hover:bg-green-200' =>
                                        $week['can_book_free'] &&
                                        !$week['is_current_week'] &&
                                        !$week['is_past_week'],
                                    'bg-purple-100 border-purple-300 text-purple-800 hover:bg-purple-200' =>
                                        $week['can_book_premium'] &&
                                        !$week['can_book_free'] &&
                                        !$week['is_current_week'] &&
                                        !$week['is_past_week'],
                                    'bg-white border-gray-300 hover:bg-gray-50' =>
                                        !$week['is_bookable'] &&
                                        !$week['is_current_week'] &&
                                        !$week['is_past_week'],
                                ])>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div @class([
                                            'font-medium',
                                            'text-sm' => $compactView,
                                            '' => !$compactView,
                                        ])>Week {{ $week['week_number'] }}</div>
                                        <div @class([
                                            'opacity-75',
                                            'text-xs' => $compactView,
                                            'text-sm' => !$compactView,
                                        ])>{{ $week['formatted_range'] }}</div>
                                    </div>
                                    <div class="text-right">
                                        @if ($week['is_current_week'])
                                            <span @class([
                                                'bg-blue-200 px-2 py-1 rounded',
                                                'text-xs' => $compactView || !$compactView,
                                            ])>Current</span>
                                        @elseif($week['is_past_week'])
                                            <span @class([
                                                'bg-gray-200 px-2 py-1 rounded',
                                                'text-xs' => $compactView || !$compactView,
                                            ])>Past</span>
                                        @elseif($week['can_book_free'])
                                            <span @class([
                                                'bg-green-200 px-2 py-1 rounded',
                                                'text-xs' => $compactView || !$compactView,
                                            ])>🆓 Free</span>
                                        @elseif($week['can_book_premium'])
                                            <span @class([
                                                'bg-purple-200 px-2 py-1 rounded',
                                                'text-xs' => $compactView || !$compactView,
                                            ])>⭐ Premium</span>
                                        @else
                                            <span @class([
                                                'bg-gray-200 px-2 py-1 rounded',
                                                'text-xs' => $compactView || !$compactView,
                                            ])>🔒 Locked</span>
                                        @endif
                                    </div>
                                </div>
                            </button>
                        @endforeach
                    </div>
                @else
                    <!-- Month Picker -->
                    <div @class([
                        'grid gap-3',
                        'grid-cols-2' => $compactView,
                        'grid-cols-3' => !$compactView,
                    ])>
                        @foreach ($calendarMonths as $month)
                            <button wire:click="selectMonth('{{ $month['month_start'] }}')"
                                @class([
                                    'rounded-lg border text-center transition-all duration-200 hover:scale-105',
                                    'p-2' => $compactView,
                                    'p-4' => !$compactView,
                                    'bg-blue-100 border-blue-300 text-blue-800' => $month['is_current_month'],
                                    'bg-gray-100 border-gray-300 text-gray-500' => $month['is_past_month'],
                                    'bg-green-100 border-green-300 text-green-800 hover:bg-green-200' =>
                                        $month['can_book_free'] &&
                                        !$month['is_current_month'] &&
                                        !$month['is_past_month'],
                                    'bg-purple-100 border-purple-300 text-purple-800 hover:bg-purple-200' =>
                                        $month['can_book_premium'] &&
                                        !$month['can_book_free'] &&
                                        !$month['is_current_month'] &&
                                        !$month['is_past_month'],
                                    'bg-white border-gray-300 hover:bg-gray-50' =>
                                        !$month['is_bookable'] &&
                                        !$month['is_current_month'] &&
                                        !$month['is_past_month'],
                                ])>
                                <div @class([
                                    'font-medium',
                                    'text-sm' => $compactView,
                                    '' => !$compactView,
                                ])>{{ $month['month_name'] }}</div>
                                <div @class(['mt-1', 'text-xs' => $compactView || !$compactView])>
                                    @if ($month['is_current_month'])
                                        Current
                                    @elseif($month['is_past_month'])
                                        Past
                                    @elseif($month['booking_type'] === 'mixed')
                                        🆓⭐ @if (!$compactView)
                                            Mixed
                                        @endif
                                    @elseif($month['can_book_free'])
                                        🆓 @if (!$compactView)
                                            Free
                                        @endif
                                    @elseif($month['can_book_premium'])
                                        ⭐ @if (!$compactView)
                                            Premium
                                        @endif
                                    @else
                                        🔒 @if (!$compactView)
                                            Locked
                                        @endif
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="rounded-b-xl border-t border-gray-200 p-4">
                <div class="flex items-center justify-between">
                    @if (!$compactView)
                        <div class="text-xs text-gray-500">
                            <div class="flex items-center gap-4">
                                <span class="flex items-center gap-1">
                                    <div class="h-3 w-3 rounded border border-green-300 bg-green-100"></div>
                                    🆓 Free
                                </span>
                                <span class="flex items-center gap-1">
                                    <div class="h-3 w-3 rounded border border-purple-300 bg-purple-100"></div>
                                    ⭐ Premium
                                </span>
                                <span class="flex items-center gap-1">
                                    <div class="h-3 w-3 rounded bg-gray-100"></div>
                                    🔒 Locked
                                </span>
                            </div>
                        </div>
                    @endif
                    <button wire:click="closeDatePicker" @class([
                        'text-gray-600 hover:text-gray-800 transition-colors',
                        'px-3 py-1 text-sm' => $compactView,
                        'px-4 py-2' => !$compactView,
                    ])>
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
