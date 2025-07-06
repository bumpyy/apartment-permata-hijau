<?php

namespace App\Models;

use App\Enum\BookingStatusEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'cancellation_reason',
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
        return sprintf('BK%s-%s-%s-%s', $this->tenant_id, $this->court->id, Carbon::today()->format('Y-m-d'), strtoupper(Str::random(4)));
    }

    public static function generateBookingReference($tenantId, $courtId)
    {
        return sprintf('BK%s-%s-%s-%s', $tenantId, $courtId, Carbon::today()->format('Y-m-d'), strtoupper(Str::random(4)));
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

    /**
     * Check if a specific time slot is already booked
     * This prevents duplicate bookings across all tenants
     *
     * @param int $courtId
     * @param string $date - Date in Y-m-d format
     * @param string $startTime - Start time in H:i format
     * @return bool - True if slot is already booked
     */
    public static function isSlotBooked($courtId, $date, $startTime)
    {
        return self::where('court_id', $courtId)
            ->where('date', $date)
            ->where('start_time', $startTime)
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->exists();
    }

    /**
     * Get all booked slots for a specific court and date range
     * Useful for checking multiple slots at once
     *
     * @param int $courtId
     * @param string $startDate - Start date in Y-m-d format
     * @param string $endDate - End date in Y-m-d format
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getBookedSlotsForCourt($courtId, $startDate, $endDate)
    {
        return self::where('court_id', $courtId)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->get();
    }

    /**
     * Check for cross-court booking conflicts for a tenant
     * This prevents tenants from booking multiple courts at the same time
     *
     * @param int $tenantId
     * @param string $date - Date in Y-m-d format
     * @param string $startTime - Start time in H:i format
     * @param string $endTime - End time in H:i format
     * @param int|null $excludeCourtId - Court ID to exclude from conflict check (for updates)
     * @return array - Array of conflicting bookings with court information
     */
    public static function getCrossCourtConflicts($tenantId, $date, $startTime, $endTime, $excludeCourtId = null)
    {
        $query = self::where('tenant_id', $tenantId)
            ->where('date', $date)
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->where(function ($q) use ($startTime, $endTime) {
                // Check for overlapping time slots
                $q->where(function ($subQ) use ($startTime, $endTime) {
                    // New booking starts during existing booking
                    $subQ->where('start_time', '<=', $startTime)
                         ->where('end_time', '>', $startTime);
                })->orWhere(function ($subQ) use ($startTime, $endTime) {
                    // New booking ends during existing booking
                    $subQ->where('start_time', '<', $endTime)
                         ->where('end_time', '>=', $endTime);
                })->orWhere(function ($subQ) use ($startTime, $endTime) {
                    // New booking completely contains existing booking
                    $subQ->where('start_time', '>=', $startTime)
                         ->where('end_time', '<=', $endTime);
                });
            });

        if ($excludeCourtId) {
            $query->where('court_id', '!=', $excludeCourtId);
        }

        return $query->with('court:id,name')
            ->get()
            ->map(function ($booking) {
                return [
                    'id' => $booking->id,
                    'court_name' => $booking->court->name ?? 'Unknown Court',
                    'court_id' => $booking->court_id,
                    'start_time' => $booking->start_time->format('H:i'),
                    'end_time' => $booking->end_time->format('H:i'),
                    'booking_reference' => $booking->booking_reference,
                    'status' => $booking->status->value,
                ];
            })
            ->toArray();
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
