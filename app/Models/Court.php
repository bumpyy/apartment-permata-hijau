<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'hourly_rate',
        'light_surcharge',
        'is_active',
        'operating_hours',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'light_surcharge' => 'decimal:2',
        'is_active' => 'boolean',
        'operating_hours' => 'array',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function isOperatingAt($time)
    {
        if (!$this->operating_hours) {
            return true; // If no operating hours set, assume always open
        }

        $openTime = $this->operating_hours['open'] ?? '08:00';
        $closeTime = $this->operating_hours['close'] ?? '23:00';

        return $time >= $openTime && $time <= $closeTime;
    }
}
