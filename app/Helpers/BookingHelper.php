<?php

namespace App\Helpers;

use App\Models\Tenant;
use App\Services\BookingValidationService;
use Carbon\Carbon;

class BookingHelper
{
    /**
     * Example of how to validate a tenant's booking request
     */
    public static function validateTenantBooking(Tenant $tenant, array $slotKeys, int $courtId): array
    {
        $validationService = app(BookingValidationService::class);

        return $validationService->validateSlotSelection($tenant, $slotKeys, $courtId);
    }

    /**
     * Example of how to check if a tenant can book a specific slot
     */
    public static function canTenantBookSlot(Tenant $tenant, string $date, string $startTime, int $courtId): array
    {
        return $tenant->canBookSlot(Carbon::parse($date), $startTime, $courtId);
    }

    /**
     * Example of how to get available time slots for a date and court
     */
    public static function getAvailableSlots(int $courtId, string $date): array
    {
        $validationService = app(BookingValidationService::class);

        return $validationService->getAvailableTimeSlots($courtId, Carbon::parse($date));
    }

    /**
     * Example of how to check booking rules for a date
     */
    public static function getBookingRules(string $date): array
    {
        $validationService = app(BookingValidationService::class);
        $carbonDate = Carbon::parse($date);

        return [
            'date' => $date,
            'can_book_free' => $validationService->canBookFree($carbonDate),
            'can_book_premium' => $validationService->canBookPremium($carbonDate),
            'booking_type' => $validationService->getDateBookingType($carbonDate),
            'is_premium_booking_open' => $validationService->isPremiumBookingOpen(),
        ];
    }

    /**
     * Example of how to validate multiple bookings for a tenant
     *
     * @param  array  $bookings  Array of booking data
     */
    public static function validateMultipleBookings(Tenant $tenant, array $bookings): array
    {
        $results = [];

        foreach ($bookings as $booking) {
            $slotKey = $booking['date'].'-'.$booking['start_time'];
            $validationResult = $tenant->canBookSlot(
                Carbon::parse($booking['date']),
                $booking['start_time'],
                $booking['court_id']
            );

            $results[] = [
                'booking' => $booking,
                'slot_key' => $slotKey,
                'can_book' => $validationResult['can_book'],
                'reason' => $validationResult['reason'] ?? null,
            ];
        }

        return $results;
    }

    /**
     * Example of how to get tenant's booking quota information
     */
    public static function getTenantQuotaInfo(Tenant $tenant): array
    {
        return [
            'combined_quota' => $tenant->combined_booking_quota,
            'free_quota' => $tenant->free_booking_quota,
            'premium_quota' => $tenant->premium_booking_quota,
            'weekly_remaining' => $tenant->remaining_weekly_quota,
            'current_week_usage' => $tenant->getCurrentWeekQuotaUsage(),
        ];
    }

    /**
     * Example of how to check cross-court conflicts for a tenant
     */
    public static function checkCrossCourtConflicts(Tenant $tenant, array $slotKeys, int $excludeCourtId): array
    {
        return $tenant->checkCrossCourtConflicts($slotKeys, $excludeCourtId);
    }
}
