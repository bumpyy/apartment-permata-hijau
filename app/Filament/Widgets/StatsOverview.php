<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Today bookings', Booking::where('created_at', '=', now()->format('Y-m-d'))->count()),
            // ->description('32k increase')
            // ->descriptionIcon('heroicon-m-arrow-trending-up'),
        ];
    }
}
