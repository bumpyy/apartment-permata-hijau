<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class Premium extends Settings
{
    public int $open_date;

    public static function group(): string
    {
        return 'premium';
    }
}
