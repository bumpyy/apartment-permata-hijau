<?php

namespace App\Traits;

use App\Services\BookingValidationService;
use Carbon\Carbon;

trait HasBookingValidation
{
    /**
     * Get the booking validation service
     */
    protected function getBookingValidationService(): BookingValidationService
    {
        return app(BookingValidationService::class);
    }

    /**
     * Check if a date can be booked for free
     *
     * @param  Carbon  $date
     */
    public function canBookFree($date): bool
    {
        return $this->getBookingValidationService()->canBookFree(Carbon::parse($date));
    }

    /**
     * Check if a date can be booked for premium
     *
     * @param  Carbon  $date
     */
    public function canBookPremium($date): bool
    {
        return $this->getBookingValidationService()->canBookPremium(Carbon::parse($date));
    }

    /**
     * Check if a slot can be booked (not past date/time)
     *
     * @param  Carbon|string  $date
     * @param  string|null  $startTime
     */
    public function canBookSlot($date, $startTime = null): bool
    {
        $carbonDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $this->getBookingValidationService()->canBookSlot($carbonDate, $startTime);
    }

    /**
     * Get the booking type for a specific date
     *
     * @param  Carbon|string  $date
     */
    public function getDateBookingType($date): string
    {
        $carbonDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $this->getBookingValidationService()->getDateBookingType($carbonDate);
    }

    /**
     * Get detailed booking information for a date
     *
     * @param  Carbon|string  $date
     */
    public function getDateBookingInfo($date): array
    {
        $carbonDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $this->getBookingValidationService()->getDateBookingInfo($carbonDate);
    }

    /**
     * Check if premium booking is currently open
     */
    public function isPremiumBookingOpen(): bool
    {
        return $this->getBookingValidationService()->isPremiumBookingOpen();
    }

    /**
     * Get available time slots for a specific date and court
     *
     * @param  Carbon|string  $date
     */
    public function getAvailableTimeSlots(int $courtId, $date): array
    {
        $carbonDate = $date instanceof Carbon ? $date : Carbon::parse($date);

        return $this->getBookingValidationService()->getAvailableTimeSlots($courtId, $carbonDate);
    }

    /**
     * Check if a specific time slot is already booked
     *
     * @param  string  $date  Date in Y-m-d format
     * @param  string  $startTime  Start time in H:i format
     */
    public function isSlotAlreadyBooked(int $courtId, string $date, string $startTime): bool
    {
        return $this->getBookingValidationService()->isSlotAlreadyBooked($courtId, $date, $startTime);
    }

    /**
     * Generate time slots for a specific date
     *
     * @param  Carbon|string  $date
     */
    public function generateTimeSlotsForDate($date, int $courtId, array $selectedSlots = []): array
    {
        $carbonDate = $date instanceof Carbon ? $date : Carbon::parse($date);
        $availableSlots = $this->getAvailableTimeSlots($courtId, $carbonDate);

        // Mark selected slots
        foreach ($availableSlots as &$slot) {
            $slot['is_selected'] = in_array($slot['slot_key'], $selectedSlots);
        }

        return $availableSlots;
    }

    /**
     * Validate slot selection for a tenant
     *
     * @param  \App\Models\Tenant  $tenant
     */
    public function validateTenantSlotSelection($tenant, array $slotKeys, int $courtId): array
    {
        return $tenant->validateSlotSelection($slotKeys, $courtId);
    }

    /**
     * Check for cross-court conflicts for a tenant
     *
     * @param  \App\Models\Tenant  $tenant
     */
    public function checkCrossCourtConflicts($tenant, array $slotKeys, int $excludeCourtId): array
    {
        return $tenant->checkCrossCourtConflicts($slotKeys, $excludeCourtId);
    }

    /**
     * Get booking counts for a specific date
     *
     * @param  Carbon|string  $date
     */
    public function getDateBookingCounts($date, int $courtId, array $bookedSlots = [], array $preliminaryBookedSlots = [], array $selectedSlots = []): array
    {
        $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');

        // Count confirmed bookings for this date
        $bookedCount = collect($bookedSlots)
            ->filter(function ($slot) use ($dateStr) {
                return str_starts_with($slot['key'], $dateStr);
            })
            ->count();

        // Count pending bookings for this date
        $pendingCount = collect($preliminaryBookedSlots)
            ->filter(function ($slot) use ($dateStr) {
                return str_starts_with($slot['key'], $dateStr);
            })
            ->count();

        // Count currently selected slots for this date
        $selectedCount = collect($selectedSlots)
            ->filter(function ($slot) use ($dateStr) {
                return str_starts_with($slot, $dateStr);
            })
            ->count();

        // Calculate available slots (total 14 slots: 8am-10pm)
        $totalSlots = 14;
        $availableCount = $totalSlots - $bookedCount - $pendingCount;

        return [
            'booked' => $bookedCount,
            'pending' => $pendingCount,
            'selected' => $selectedCount,
            'available' => max(0, $availableCount),
        ];
    }

    /**
     * Generate days for weekly view
     */
    public function generateWeekDays(Carbon $weekStart): array
    {
        $weekDays = [];
        $start = $weekStart->copy();

        for ($i = 0; $i < 7; $i++) {
            $date = $start->copy()->addDays($i);
            $weekDays[] = [
                'date' => $date->format('Y-m-d'),
                'day_name' => $date->format('D'), // Mon, Tue, etc.
                'day_number' => $date->format('j'), // 1, 2, 3, etc.
                'month_name' => $date->format('M'), // Jan, Feb, etc.
                'is_today' => $date->isToday(),
                'is_past' => $date->isPast(),
                'is_bookable' => $this->canBookSlot($date),
                'can_book_free' => $this->canBookFree($date),
                'can_book_premium' => $this->canBookPremium($date),
                'formatted_date' => $date->format('M j, Y'),
            ];
        }

        return $weekDays;
    }

    /**
     * Generate days for monthly view
     */
    public function generateMonthDays(Carbon $monthStart, array $bookedSlots = [], array $preliminaryBookedSlots = [], array $selectedSlots = []): array
    {
        $monthDays = [];

        // Start from Monday of first week, end on Sunday of last week
        $start = $monthStart->copy()->startOfWeek();
        $end = $monthStart->copy()->endOfMonth()->endOfWeek();

        while ($start <= $end) {
            // Get booking counts for this date
            $bookingCounts = $this->getDateBookingCounts($start, 0, $bookedSlots, $preliminaryBookedSlots, $selectedSlots);

            $monthDays[] = [
                'date' => $start->format('Y-m-d'),
                'day_number' => $start->format('j'),
                'is_current_month' => $start->month === $monthStart->month,
                'is_today' => $start->isToday(),
                'is_past' => $start->isPast(),
                'is_bookable' => $this->canBookSlot($start),
                'can_book_free' => $this->canBookFree($start),
                'can_book_premium' => $this->canBookPremium($start),
                'booking_type' => $this->getDateBookingType($start),
                'booked_count' => $bookingCounts['booked'],
                'pending_count' => $bookingCounts['pending'],
                'selected_count' => $bookingCounts['selected'],
                'available_count' => $bookingCounts['available'],
            ];
            $start->addDay();
        }

        return $monthDays;
    }
}
