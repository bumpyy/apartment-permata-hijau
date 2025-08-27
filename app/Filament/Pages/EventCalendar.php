<?php

namespace App\Filament\Pages;

use App\Livewire\CustomCalendarWidget;
use BackedEnum;
use Filament\Pages\Page;

class EventCalendar extends Page
{
    protected string $view = 'filament.pages.event-calendar';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    // public static function shouldRegisterNavigation(): bool
    // {
    //     // TODO: Check https://github.com/GuavaCZ/calendar/tree/beta periodically for v4 stable release,
    //     // for now Only show in local environment to avoid issues with the calendar widget.
    //     return app()->environment('local');
    // }

    public static function getWidgets(): array
    {
        return [
            CustomCalendarWidget::class,
        ];
    }
}
