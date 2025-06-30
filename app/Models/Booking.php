<?php

namespace App\Models;

use App\Enum\BookingStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'court_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'booking_type',
        'booking_week_start',
        'price',
        'is_light_required',
        'light_surcharge',
        'booking_reference',
        'notes',
        'approved_by',
        'approved_at',
        'cancelled_by',
        'cancelled_at',
        'edited_by',
        'edited_at',
    ];

    protected $casts = [
        'date' => 'date',
        'booking_week_start' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'price' => 'int',
        'light_surcharge' => 'int',
        'is_light_required' => 'boolean',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'edited_at' => 'datetime',
        'status' => BookingStatusEnum::class,
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function generateReference()
    {
        return 'A'.str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    public function calculatePrice()
    {
        $basePrice = $this->court->hourly_rate;
        $lightSurcharge = 0;

        if (Carbon::parse($this->start_time)->hour >= 18) {
            $lightSurcharge = $this->court->light_surcharge;
            $this->is_light_required = true;
        }

        $this->price = $basePrice;
        $this->light_surcharge = $lightSurcharge;

        return $basePrice + $lightSurcharge;
    }

    public function getTotalPriceAttribute()
    {
        return $this->price + $this->light_surcharge;
    }

    public function getBookingTypeDisplayAttribute()
    {
        return strtoupper($this->booking_type);
    }

    public function getStatusDisplayAttribute()
    {
        return match ($this->status->value) {
            'pending' => 'PENDING',
            'confirmed' => $this->total_price > 0 ? 'PAID' : 'FREE',
            'cancelled' => 'CANCELLED',
            default => strtoupper($this->status->value)
        };
    }

    public static function getBookedDaysForTenant($tenantId, $startDate = null, $endDate = null)
    {
        $query = self::where('tenant_id', $tenantId)
            ->where('status', '!=', 'cancelled');

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        return $query->get()->groupBy(function ($booking) {
            return $booking->date->format('Y-m-d');
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($booking) {
            // Automatically set booking week start
            $bookingDate = Carbon::parse($booking->date);
            $booking->booking_week_start = $bookingDate->startOfWeek()->format('Y-m-d');

            // Determine booking type based on date
            if (! $booking->booking_type) {
                $daysFromNow = Carbon::now()->diffInDays($bookingDate, false);
                $booking->booking_type = $daysFromNow <= 7 ? 'free' : 'premium';
            }
        });
    }
}
