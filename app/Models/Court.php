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
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'light_surcharge' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function getAvailableSlots($date)
    {
        $bookedSlots = $this->bookings()
            ->where('date', $date)
            ->where('status', '!=', 'cancelled')
            ->pluck('start_time')
            ->toArray();

        return $bookedSlots;
    }
}
