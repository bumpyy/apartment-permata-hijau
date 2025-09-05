<?php

namespace App\Filament\Widgets;

use App\Models\Booking;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Today new bookings', Booking::whereDate('created_at', Carbon::today())->count()),
            // ->description('32k increase')
            // ->descriptionIcon('heroicon-m-arrow-trending-up'),
        ];
    }
}
