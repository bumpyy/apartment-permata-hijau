<?php

namespace App\Models;

use App\Services\BookingValidationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class Tenant extends Authenticatable implements HasMedia
{
    use HasFactory, HasRoles, InteractsWithMedia, Notifiable;

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

    /**
     * Get the bookings for the tenant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function getDisplayNameAttribute()
    {
        return $this->tenant_id ?? $this->name;
    }

    /**
     * Get the number of bookings made by the tenant for the current week.
     *
     * @return int
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
     * Get the remaining bookings available for the current week
     *
     * This will calculate the number of bookings already made by the tenant
     * for the current week and subtract that from the tenant's booking limit.
     * Returns the remaining bookings available for the current week.
     *
     * @return int
     */
    public function getRemainingWeeklyQuotaAttribute()
    {
        $used = $this->getCurrentWeekQuotaUsage();

        return max(0, $this->booking_limit - $used);
    }

    /**
     * Get the free booking quota for the tenant.
     *
     * This will calculate the number of free bookings already made by the
     * tenant for the current week and subtract that from the tenant's booking
     * limit. Returns the remaining free bookings available for the current week.
     *
     * @return array
     */
    public function getFreeBookingQuotaAttribute()
    {
        $used = $this->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('booking_type', 'free')
            // ->where('booking_week_start', $weekStart->format('Y-m-d'))
            ->where('date', '>=', Carbon::today()->format('Y-m-d'))
            ->get();

        return [
            'used' => $used->groupBy('date')->count(),
            'total' => $this->booking_limit,
            'remaining' => max(0, $this->booking_limit - $used->groupBy('date')->count()),
        ];
    }

    /**
     * Get the premium booking quota for the tenant.
     *
     * This will calculate the number of premium bookings already made by the
     * tenant for the current week and subtract that from the tenant's booking
     * limit. Returns the remaining premium bookings available for the current week.
     *
     * @return array
     */
    public function getPremiumBookingQuotaAttribute()
    {
        $used = $this->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('booking_type', 'premium')
            ->where('date', '>=', Carbon::today()->format('Y-m-d'))
            ->get();

        // Premium quota is calculated based on weeks ahead
        $weeksAhead = 4; // 1 month = ~4 weeks
        $totalPremiumQuota = $this->booking_limit * $weeksAhead;

        return [
            'used' => $used->groupBy('date')->count(),
            'total' => $totalPremiumQuota,
            'remaining' => max(0, $totalPremiumQuota - $used->groupBy('date')->count()),
        ];
    }

    /**
     * Get the combined booking quota for the tenant.
     *
     * This will calculate the number of bookings already made by the
     * tenant for the current week and subtract that from the tenant's booking
     * limit. Returns the remaining bookings available for the current week as
     * a combined quota.
     *
     * @return array
     */
    public function getCombinedBookingQuotaAttribute()
    {
        $used = $this->bookings()
            ->where('status', '!=', 'cancelled')
            ->where('date', '>=', Carbon::today()->format('Y-m-d'))
            ->get();

        return [
            'used' => $used->groupBy('date')->count(),
            'total' => $this->booking_limit,
            'remaining' => max(0, $this->booking_limit - $used->groupBy('date')->count()),
            'dates' => $used,
        ];
    }

    /**
     * Determine if a tenant can make a specific type of booking.
     *
     * This method checks if the tenant can make a booking of the specified type
     * ('free' or 'premium') for the given date. It ensures that the booking
     * falls within the allowed advance booking period (7 days for free bookings,
     * 1 month for premium bookings) and does not exceed the tenant's weekly
     * booking limit.
     *
     * @param  string  $date  The date for which the booking is intended, in Y-m-d format.
     * @param  string  $bookingType  The type of booking ('free' or 'premium'). Defaults to 'free'.
     * @param  int  $slotsCount  The number of slots the tenant wants to book. Defaults to 1.
     * @return array An associative array containing:
     *               - 'can_book': A boolean indicating if the booking can be made.
     *               - 'available_slots': The number of available slots for the week.
     *               - 'reason': A string containing the reason if booking cannot be made.
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
     * Check if the tenant can make a booking with the given number of slots.
     *
     * @param  int  $slotsCount  The number of slots to check.
     * @return array A result set containing a boolean indicating whether the booking can be made
     *               and an integer representing the number of slots available.
     *               If the booking cannot be made, a reason string is also provided.
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

    /**
     * Validate slot selection using the BookingValidationService
     *
     * @param array $slotKeys Array of slot keys in format "YYYY-MM-DD-HH:MM"
     * @param int $courtId
     * @return array Validation result
     */
    public function validateSlotSelection(array $slotKeys, int $courtId): array
    {
        $validationService = app(BookingValidationService::class);
        return $validationService->validateSlotSelection($this, $slotKeys, $courtId);
    }

    /**
     * Check if tenant can book a specific date and time
     *
     * @param Carbon $date
     * @param string $startTime
     * @param int $courtId
     * @return array Validation result
     */
    public function canBookSlot(Carbon $date, string $startTime = null, int $courtId = null): array
    {
        $validationService = app(BookingValidationService::class);

        // Check if the slot is bookable
        if (!$validationService->canBookSlot($date, $startTime)) {
            return [
                'can_book' => false,
                'reason' => 'This slot is not available for booking.',
                'details' => $validationService->getDateBookingInfo($date)
            ];
        }

        // Check if slot is already booked (if court is provided)
        if ($courtId && $startTime) {
            if ($validationService->isSlotAlreadyBooked($courtId, $date->format('Y-m-d'), $startTime)) {
                return [
                    'can_book' => false,
                    'reason' => 'This time slot is already booked.',
                    'details' => $validationService->getDateBookingInfo($date)
                ];
            }
        }

        // Get booking type for this date
        $bookingType = $validationService->getDateBookingType($date);

        // Validate tenant-specific constraints
        return $this->canMakeSpecificTypeBooking($date->format('Y-m-d'), $bookingType, 1);
    }

    /**
     * Check if tenant can book multiple slots
     *
     * @param array $slotKeys Array of slot keys
     * @param int $courtId
     * @return array Validation result
     */
    public function canBookMultipleSlots(array $slotKeys, int $courtId): array
    {
        $validationService = app(BookingValidationService::class);
        return $validationService->validateSlotSelection($this, $slotKeys, $courtId);
    }

    /**
     * Get available time slots for a specific date and court
     *
     * @param int $courtId
     * @param Carbon $date
     * @return array
     */
    public function getAvailableTimeSlots(int $courtId, Carbon $date): array
    {
        $validationService = app(BookingValidationService::class);
        return $validationService->getAvailableTimeSlots($courtId, $date);
    }

    /**
     * Check if tenant can book free slots on a specific date
     *
     * @param Carbon $date
     * @return bool
     */
    public function canBookFree(Carbon $date): bool
    {
        $validationService = app(BookingValidationService::class);
        return $validationService->canBookFree($date);
    }

    /**
     * Check if tenant can book premium slots on a specific date
     *
     * @param Carbon $date
     * @return bool
     */
    public function canBookPremium(Carbon $date): bool
    {
        $validationService = app(BookingValidationService::class);
        return $validationService->canBookPremium($date);
    }

    /**
     * Get booking type for a specific date
     *
     * @param Carbon $date
     * @return string
     */
    public function getDateBookingType(Carbon $date): string
    {
        $validationService = app(BookingValidationService::class);
        return $validationService->getDateBookingType($date);
    }

    /**
     * Get detailed booking information for a date
     *
     * @param Carbon $date
     * @return array
     */
    public function getDateBookingInfo(Carbon $date): array
    {
        $validationService = app(BookingValidationService::class);
        return $validationService->getDateBookingInfo($date);
    }

    /**
     * Check for cross-court conflicts
     *
     * @param array $slotKeys
     * @param int $excludeCourtId
     * @return array
     */
    public function checkCrossCourtConflicts(array $slotKeys, int $excludeCourtId): array
    {
        $validationService = app(BookingValidationService::class);
        return $validationService->checkCrossCourtConflicts($this, $slotKeys, $excludeCourtId);
    }

    /**
     * Get the user's initials
     *
     * This method takes the tenant's full name, splits it into parts (assuming
     * the parts are separated by spaces), takes the first letter of each part,
     * and combines them into a single string.
     *
     * @return string The user's initials.
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    /**
     * Get the tenant's avatar URL or initials.
     *
     * This function retrieves the URL of the tenant's profile picture from the
     * Spatie Media Library. If the tenant doesn't have a profile picture, it
     * returns the tenant's initials.
     *
     * @return string The URL of the profile picture or the tenant's initials.
     */
    public function avatar(): string
    {
        $profilePictureUrl = $this->getFirstMediaUrl('profile_picture');

        return $profilePictureUrl ?: $this->initials();
    }

    /**
     * Setup the model event listeners.
     *
     * When a tenant is created, a tenant_id is generated based on the
     * maximum id of the tenants table. The id is padded with leading zeros
     * to three digits.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->tenant_id)) {
                $tenant->tenant_id = 'tenant#'.str_pad(
                    Tenant::max('id') + 1,
                    3,
                    '0',
                    STR_PAD_LEFT
                );
            }
        });
    }

    /**
     * Register the media collections for the tenant.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_picture')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpg', 'image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }
}
