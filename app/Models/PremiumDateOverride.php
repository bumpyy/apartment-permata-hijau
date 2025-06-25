<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PremiumDateOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'note',
    ];

    /**
     * Get the premium booking date for the current month: override if exists, else fallback to 25th.
     */
    public static function getCurrentMonthPremiumDate(): \Carbon\Carbon
    {
        $override = self::whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->first();
        if ($override) {
            return \Carbon\Carbon::parse($override->date);
        }

        // Fallback to 25th of current month
        return now()->copy()->day(25);
    }
}
