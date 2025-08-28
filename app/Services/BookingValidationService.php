<?php

namespace App\Services;

use App\Enum\BookingStatusEnum;
use App\Models\Booking;
use App\Models\Court;
use App\Models\PremiumDateOverride;
use App\Models\Tenant;
use Carbon\Carbon;

class BookingValidationService
{
    /**
     * Validate if a tenant can book specific slots
     *
     * @param  array  $slotKeys  Array of slot keys in format "YYYY-MM-DD-HH:MM"
     * @return array Validation result with can_book, warnings, and conflicts
     */
    public function validateSlotSelection(Tenant $tenant, array $slotKeys, int $courtId): array
    {
        $warnings = [];
        $conflicts = [];
        $canBook = true;

        // Extract unique dates from slot keys
        $selectedDates = collect($slotKeys)
            ->map(function ($slot) {
                $parts = explode('-', $slot);

                return $parts[0].'-'.$parts[1].'-'.$parts[2];
            })
            ->unique()
            ->values();

        // Get existing bookings for the tenant
        $existingBookings = Booking::getBookedDaysForTenant($tenant->id, Carbon::today()->format('Y-m-d'));
        $bookedDaysCount = $existingBookings->count();

        // Check daily quota (2 hours max per day)
        foreach ($selectedDates as $date) {
            $existingBookingsForDate = $existingBookings->has($date) ? $existingBookings->get($date)->count() : 0;
            $selectedSlotsForThisDate = collect($slotKeys)
                ->filter(function ($slot) use ($date) {
                    return str_starts_with($slot, $date);
                })
                ->count();

            if ($existingBookingsForDate + $selectedSlotsForThisDate > 2) {
                $warnings[] = 'Maximum 2 hours per day allowed.';
                $canBook = false;
                break;
            }
        }

        // Check 3 distinct days rule
        $newDaysCount = 0;
        foreach ($selectedDates as $date) {
            if (! $existingBookings->has($date)) {
                $newDaysCount++;
            }
        }

        if ($bookedDaysCount + $newDaysCount > 3) {
            $warnings[] = 'You cannot book for more than 3 distinct days.';
            $canBook = false;
        }

        // Check for booking conflicts
        foreach ($slotKeys as $slotKey) {
            $parts = explode('-', $slotKey);
            if (count($parts) >= 4) {
                $date = $parts[0].'-'.$parts[1].'-'.$parts[2];
                $startTime = count($parts) == 4 ? $parts[3] : $parts[3].':'.$parts[4];

                if ($this->isSlotAlreadyBooked($courtId, $date, $startTime)) {
                    $conflicts[] = [
                        'slot_key' => $slotKey,
                        'date' => $date,
                        'start_time' => $startTime,
                        'message' => 'This time slot was just booked by another tenant.',
                    ];
                    $canBook = false;
                }
            }
        }

        // Check for cross-court conflicts
        $crossCourtConflicts = $this->checkCrossCourtConflicts($tenant, $slotKeys, $courtId);
        if (! empty($crossCourtConflicts)) {
            $conflicts = array_merge($conflicts, $crossCourtConflicts);
            $canBook = false;
        }

        return [
            'can_book' => $canBook,
            'warnings' => $warnings,
            'conflicts' => $conflicts,
            'selected_dates' => $selectedDates->toArray(),
            'booked_days_count' => $bookedDaysCount,
            'new_days_count' => $newDaysCount,
        ];
    }

    /**
     * Check if a specific time slot is already booked
     *
     * @param  string  $date  Date in Y-m-d format
     * @param  string  $startTime  Start time in H:i format
     */
    public function isSlotAlreadyBooked(int $courtId, string $date, string $startTime): bool
    {
        return Booking::isSlotBooked($courtId, $date, $startTime);
    }

    /**
     * Check for cross-court booking conflicts
     */
    public function checkCrossCourtConflicts(Tenant $tenant, array $slotKeys, int $excludeCourtId): array
    {
        $conflicts = [];

        foreach ($slotKeys as $slotKey) {
            $parts = explode('-', $slotKey);
            if (count($parts) >= 4) {
                $date = $parts[0].'-'.$parts[1].'-'.$parts[2];
                $startTime = count($parts) == 4 ? $parts[3] : $parts[3].':'.$parts[4];
                $endTime = Carbon::createFromFormat('H:i', $startTime)->addHour()->format('H:i');

                $crossCourtBookings = Booking::getCrossCourtConflicts(
                    $tenant->id,
                    $date,
                    $startTime,
                    $endTime,
                    $excludeCourtId
                );

                foreach ($crossCourtBookings as $booking) {
                    $conflicts[] = [
                        'slot_key' => $slotKey,
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'conflicting_booking' => $booking,
                        'message' => "You have a booking on {$booking->court->name} at the same time.",
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if a date can be booked for free
     */
    public function canBookFree(Carbon $date): bool
    {
        $nextWeekStart = now()->addWeek()->startOfWeek();
        $nextWeekEnd = now()->addWeek()->endOfWeek();

        return $date->between($nextWeekStart, $nextWeekEnd);
    }

    /**
     * Check if a date can be booked for premium
     */
    public function canBookPremium(Carbon $date): bool
    {
        // Premium booking is currently disabled
        // if (request()->routeIs('facilities.*')) {
        //     return false;
        // }

        $nextWeekEnd = now()->addWeek()->endOfWeek();
        $premiumEnd = now()->addMonth()->endOfMonth();

        return $date->gt($nextWeekEnd) && $date->lte($premiumEnd) && $this->isPremiumBookingOpen();
    }

    /**
     * Check if premium booking is currently open
     */
    public function isPremiumBookingOpen(): bool
    {
        $currentDate = now();
        $premiumBookingDate = PremiumDateOverride::getCurrentMonthPremiumDate();

        if ($currentDate->toDateString() > $premiumBookingDate->toDateString()) {
            $nextMonthPremiumDate = PremiumDateOverride::whereMonth('date', $currentDate->copy()->addMonth()->month)
                ->whereYear('date', $currentDate->copy()->addMonth()->year)
                ->first();

            $premiumBookingDate = $nextMonthPremiumDate ? Carbon::parse($nextMonthPremiumDate->date) : $currentDate->copy()->addMonth()->day(25);
        }

        return now()->format('Y-m-d') === $premiumBookingDate->format('Y-m-d');
    }

    /**
     * Check if a slot can be booked (not past date/time)
     */
    public function canBookSlot(Carbon $date, ?string $startTime = null): bool
    {
        // Check if date is in the past
        if ($date->isPast()) {
            return false;
        }

        // If time is provided, check if the specific time slot is in the past
        if ($startTime) {
            $slotDateTime = $date->copy()->setTimeFromTimeString($startTime);
            if ($slotDateTime->isPast()) {
                return false;
            }
        }

        return $this->canBookFree($date) || $this->canBookPremium($date);
    }

    /**
     * Get the booking type for a specific date
     */
    public function getDateBookingType(Carbon $date): string
    {
        if ($this->canBookFree($date)) {
            return 'free';
        }
        if ($this->canBookPremium($date)) {
            return 'premium';
        }

        return 'none';
    }

    /**
     * Get detailed booking information for a date
     */
    public function getDateBookingInfo(Carbon $date): array
    {
        return [
            'can_book_free' => $this->canBookFree($date),
            'can_book_premium' => $this->canBookPremium($date),
            'is_bookable' => $this->canBookSlot($date),
            'booking_type' => $this->getDateBookingType($date),
        ];
    }

    /**
     * Validate if a tenant can make a booking with specific constraints
     */
    public function validateTenantBooking(Tenant $tenant, Carbon $date, string $bookingType = 'free', int $slotsCount = 1): array
    {
        // Check if the date is bookable
        if (! $this->canBookSlot($date)) {
            return [
                'can_book' => false,
                'reason' => 'This date is not available for booking.',
                'details' => $this->getDateBookingInfo($date),
            ];
        }

        // Check if the booking type is allowed for this date
        $allowedType = $this->getDateBookingType($date);
        if ($bookingType !== $allowedType) {
            return [
                'can_book' => false,
                'reason' => "This date only allows {$allowedType} bookings.",
                'details' => $this->getDateBookingInfo($date),
            ];
        }

        // Use tenant's existing validation methods
        return $tenant->canMakeSpecificTypeBooking($date->format('Y-m-d'), $bookingType, $slotsCount);
    }

    /**
     * Get available time slots for a specific date and court
     */
    public function getAvailableTimeSlots(int $courtId, Carbon $date): array
    {
        $startTime = Carbon::parse('06:00');
        $endTime = Carbon::parse('22:00');
        $interval = 60; // 60-minute slots

        $availableSlots = [];

        // Get existing bookings for this date
        $bookedSlotsForDate = Booking::where('court_id', $courtId)
            ->where('date', $date->format('Y-m-d'))
            ->where('status', '!=', BookingStatusEnum::CANCELLED)
            ->with(['tenant:id,name,tenant_id'])
            ->get();

        $bookedBySlotsForDate = $bookedSlotsForDate;

        $bookedSlotsForDate = $bookedSlotsForDate->pluck('start_time')
            ->map(function ($time) {
                return $time->format('H:i');
            })
            ->toArray();

        // Generate time slots
        while ($startTime <= $endTime) {
            $time = $startTime->format('H:i');
            $slotKey = $date->format('Y-m-d').'-'.$time;
            $slotType = $this->getDateBookingType($date);
            $isBooked = in_array($time, $bookedSlotsForDate);
            $isPast = $startTime->copy()->setDateFrom($date)->isPast();

            $availableSlots[] = [
                'start_time' => $time,
                'end_time' => $startTime->copy()->addHour()->format('H:i'),
                'slot_key' => $slotKey,
                'slot_type' => $slotType,
                'is_available' => ! $isBooked && ! $isPast && $this->canBookSlot($date),
                'is_booked' => $isBooked,
                'booked_by' => $isBooked ? ($bookedBySlotsForDate->firstWhere('start_time', $startTime)->tenant->tenant_id ?? '') : null,
                'is_past' => $isPast,
                'is_peak' => $startTime->hour >= 18, // After 6pm = peak hours
            ];

            $startTime->addMinutes($interval);
        }

        return $availableSlots;
    }
}
