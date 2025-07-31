<?php

namespace App\Filament\Pages;

use App\NavigationGroup;
use Filament\Pages\Page;
use UnitEnum;

class PremiumSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Settings;

    protected string $view = 'filament.pages.premium-settings';
}
