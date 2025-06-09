<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class Tenant extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $guard = 'tenant';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'password',
        'booking_limit',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'booking_limit' => 'integer',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function getDisplayNameAttribute()
    {
        return $this->tenant_id ?? $this->name;
    }

    /**
     * Get current week's booking quota usage
     */
    public function getCurrentWeekQuotaUsage()
    {
        $weekStart = Carbon::now()->startOfWeek();

        return $this->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('booking_week_start', $weekStart->format('Y-m-d'))
            ->count();
    }

    /**
     * Get remaining quota for current week
     */
    public function getRemainingWeeklyQuotaAttribute()
    {
        $used = $this->getCurrentWeekQuotaUsage();

        return max(0, $this->booking_limit - $used);
    }

    /**
     * Get free booking quota (current week only)
     */
    public function getFreeBookingQuotaAttribute()
    {
        $weekStart = Carbon::now()->startOfWeek();

        $used = $this->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('booking_type', 'free')
            // ->where('booking_week_start', $weekStart->format('Y-m-d'))
            ->where('date', '>=', Carbon::now())
            ->count();

        return [
            'used' => $used,
            'total' => $this->booking_limit,
            'remaining' => max(0, $this->booking_limit - $used),
        ];
    }

    /**
     * Get premium booking quota (across multiple weeks)
     */
    public function getPremiumBookingQuotaAttribute()
    {
        $used = $this->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('booking_type', 'premium')
            ->where('date', '>=', Carbon::now())
            ->count();

        // Premium quota is calculated based on weeks ahead
        $weeksAhead = 4; // 1 month = ~4 weeks
        $totalPremiumQuota = $this->booking_limit * $weeksAhead;

        return [
            'used' => $used,
            'total' => $totalPremiumQuota,
            'remaining' => max(0, $totalPremiumQuota - $used),
        ];
    }

    /**
     * Get total booking quota
     */
    public function getCombinedBookingQuotaAttribute()
    {
        $used = $this->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('date', '>=', Carbon::now())
            ->count();

        return [
            'used' => $used,
            'total' => $this->booking_limit,
            'remaining' => max(0, $this->booking_limit - $used),
        ];
    }

    /**
     * Check if tenant can make a booking for specific date and type
     */
    public function canMakeSpecificTypeBooking($date, $bookingType = 'free', $slotsCount = 1)
    {
        $bookingDate = Carbon::parse($date);
        $weekStart = $bookingDate->startOfWeek();

        // Check weekly quota for the target week
        $weeklyUsed = $this->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('booking_week_start', $weekStart->format('Y-m-d'))
            ->count();

        $availableInWeek = max(0, $this->booking_limit - $weeklyUsed);

        if ($bookingType === 'free') {
            // Free booking: check if date is within 7 days
            $maxFreeDate = Carbon::now()->addDays(7);
            if ($bookingDate->gt($maxFreeDate)) {
                return [
                    'can_book' => false,
                    'reason' => 'Free booking only available up to 7 days in advance',
                ];
            }
        } else {
            // Premium booking: check if date is within 1 month
            $maxPremiumDate = Carbon::now()->addMonth();
            if ($bookingDate->gt($maxPremiumDate)) {
                return [
                    'can_book' => false,
                    'reason' => 'Premium booking only available up to 1 month in advance',
                ];
            }
        }

        return [
            'can_book' => $availableInWeek >= $slotsCount,
            'available_slots' => $availableInWeek,
            'reason' => $availableInWeek < $slotsCount ? "Only {$availableInWeek} slots available for this week" : null,
        ];
    }

    /**
     * Check if tenant can make a booking
     */
    public function canMakeBooking($slotsCount = 1)
    {
        $quota = $this->combined_booking_quota;

        if ($quota['remaining'] < $slotsCount) {
            return [
                'can_book' => false,
                'available_slots' => $quota['remaining'],
                'reason' => "Only {$quota['remaining']} slots available out of your limit of {$quota['total']}",
            ];
        }

        return [
            'can_book' => true,
            'available_slots' => $quota['remaining'],
            'reason' => null,
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn(string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->tenant_id)) {
                $tenant->tenant_id = 'tenant#' . str_pad(
                    Tenant::max('id') + 1,
                    3,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }
}
