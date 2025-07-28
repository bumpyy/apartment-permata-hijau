<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;

class BookingPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected string $view = 'filament.pages.booking-page';
}
