<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'court_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'price',
        'is_light_required',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'price' => 'decimal:2',
        'is_light_required' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForCourt($query, $courtId)
    {
        return $query->where('court_id', $courtId);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['confirmed', 'preliminary']);
    }

    public function calculatePrice()
    {
        $basePrice = $this->court->hourly_rate;
        $lightSurcharge = 0;

        // Add light surcharge for bookings after 6 PM
        if (Carbon::parse($this->start_time)->hour >= 18) {
            $lightSurcharge = $this->court->light_surcharge;
            $this->is_light_required = true;
        }

        return $basePrice + $lightSurcharge;
    }
}
