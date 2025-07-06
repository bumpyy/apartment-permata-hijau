

@if ($isLoggedIn && !empty($quotaInfo))
    <div @class([
        'mb-6 rounded-xl border border-blue-200 bg-gradient-to-r from-blue-50 to-blue-100 shadow-sm',
        'p-3' => $compactView,
        'p-6' => !$compactView,
    ])>
        <div class="flex items-center justify-between">
            <div>
                <h3 @class([
                    'font-bold text-blue-800',
                    'text-sm' => $compactView,
                    'text-lg' => !$compactView,
                ])>Weekly Quota</h3>
                @if (!$compactView)
                    <p class="text-sm text-blue-600">Maximum 3 distinct days, 2 hours per day</p>
                @endif
            </div>
            <div class="text-right">
                <div @class([
                    'font-bold text-blue-600',
                    'text-xl' => $compactView,
                    'text-3xl' => !$compactView,
                ])>
                    {{ $quotaInfo['weekly_used'] ?? 0 }}/{{ $quotaInfo['weekly_total'] ?? 3 }}
                </div>
                <div @class([
                    'text-blue-600',
                    'text-xs' => $compactView,
                    'text-sm' => !$compactView,
                ])>Days used</div>
            </div>
        </div>
        @if (($quotaInfo['weekly_remaining'] ?? 0) > 0)
            <div @class([
                'mt-2 text-green-600',
                'text-xs' => $compactView,
                'text-sm' => !$compactView,
            ])>
                ✅ You can book {{ $quotaInfo['weekly_remaining'] }} more days this week
            </div>
        @else
            <div @class([
                'mt-2 text-red-600',
                'text-xs' => $compactView,
                'text-sm' => !$compactView,
            ])>
                ⚠️ You have reached your booking limit
            </div>
        @endif
    </div>
@endif
