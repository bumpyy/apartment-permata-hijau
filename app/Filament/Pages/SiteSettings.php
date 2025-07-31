<?php

namespace App\Filament\Pages;

use App\NavigationGroup;
use Filament\Pages\Page;
use UnitEnum;

class SiteSettings extends Page
{
    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Settings;

    protected string $view = 'filament.pages.site-settings';
}
