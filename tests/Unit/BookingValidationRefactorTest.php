<?php

namespace Tests\Unit;

use App\Helpers\BookingHelper;
use App\Models\Tenant;
use App\Services\BookingValidationService;
use App\Traits\HasBookingValidation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingValidationRefactorTest extends TestCase
{
    use RefreshDatabase, HasBookingValidation;

    public function test_booking_validation_service_can_be_instantiated()
    {
        $service = app(BookingValidationService::class);
        $this->assertInstanceOf(BookingValidationService::class, $service);
    }

    public function test_can_book_free_method_works()
    {
        // Test next week date (should be bookable for free)
        $nextWeekDate = now()->addWeek()->startOfWeek()->addDays(2); // Wednesday of next week
        $this->assertTrue($this->canBookFree($nextWeekDate));

        // Test current week date (should not be bookable for free)
        $currentWeekDate = now()->startOfWeek()->addDays(2); // Wednesday of current week
        $this->assertFalse($this->canBookFree($currentWeekDate));
    }

    public function test_can_book_premium_method_returns_false()
    {
        // Premium booking is currently disabled
        $futureDate = now()->addMonth();
        $this->assertFalse($this->canBookPremium($futureDate));
    }

    public function test_get_date_booking_type_method()
    {
        // Test free booking type
        $nextWeekDate = now()->addWeek()->startOfWeek()->addDays(2);
        $this->assertEquals('free', $this->getDateBookingType($nextWeekDate));

        // Test none booking type for past date
        $pastDate = now()->subDays(1);
        $this->assertEquals('none', $this->getDateBookingType($pastDate));
    }

    public function test_tenant_model_has_new_validation_methods()
    {
        $tenant = Tenant::factory()->create(['booking_limit' => 3]);

        // Test that tenant has the new validation methods
        $this->assertTrue(method_exists($tenant, 'validateSlotSelection'));
        $this->assertTrue(method_exists($tenant, 'canBookSlot'));
        $this->assertTrue(method_exists($tenant, 'canBookMultipleSlots'));
        $this->assertTrue(method_exists($tenant, 'getAvailableTimeSlots'));
        $this->assertTrue(method_exists($tenant, 'checkCrossCourtConflicts'));
    }

    public function test_booking_helper_methods_work()
    {
        $tenant = Tenant::factory()->create(['booking_limit' => 3]);

        // Test helper methods
        $this->assertTrue(method_exists(BookingHelper::class, 'validateTenantBooking'));
        $this->assertTrue(method_exists(BookingHelper::class, 'canTenantBookSlot'));
        $this->assertTrue(method_exists(BookingHelper::class, 'getAvailableSlots'));
        $this->assertTrue(method_exists(BookingHelper::class, 'getBookingRules'));
        $this->assertTrue(method_exists(BookingHelper::class, 'getTenantQuotaInfo'));
    }

    public function test_trait_methods_are_available()
    {
        // Test that trait methods are available in this test class
        $this->assertTrue(method_exists($this, 'canBookFree'));
        $this->assertTrue(method_exists($this, 'canBookPremium'));
        $this->assertTrue(method_exists($this, 'canBookSlot'));
        $this->assertTrue(method_exists($this, 'getDateBookingType'));
        $this->assertTrue(method_exists($this, 'getDateBookingInfo'));
        $this->assertTrue(method_exists($this, 'isPremiumBookingOpen'));
    }

    public function test_validation_service_validate_slot_selection()
    {
        $tenant = Tenant::factory()->create(['booking_limit' => 3]);
        $service = app(BookingValidationService::class);

        // Test with empty slots (should be valid)
        $result = $service->validateSlotSelection($tenant, [], 1);
        $this->assertTrue($result['can_book']);
        $this->assertEmpty($result['warnings']);
        $this->assertEmpty($result['conflicts']);

        // Test with valid slots
        $nextWeekDate = now()->addWeek()->startOfWeek()->addDays(2)->format('Y-m-d');
        $slotKeys = [$nextWeekDate . '-10:00'];

        $result = $service->validateSlotSelection($tenant, $slotKeys, 1);
        $this->assertTrue($result['can_book']);
    }

    public function test_tenant_validate_slot_selection()
    {
        $tenant = Tenant::factory()->create(['booking_limit' => 3]);

        // Test with empty slots
        $result = $tenant->validateSlotSelection([], 1);
        $this->assertTrue($result['can_book']);

        // Test with valid slots
        $nextWeekDate = now()->addWeek()->startOfWeek()->addDays(2)->format('Y-m-d');
        $slotKeys = [$nextWeekDate . '-10:00'];

        $result = $tenant->validateSlotSelection($slotKeys, 1);
        $this->assertTrue($result['can_book']);
    }

    public function test_booking_helper_validate_tenant_booking()
    {
        $tenant = Tenant::factory()->create(['booking_limit' => 3]);

        // Test helper method
        $result = BookingHelper::validateTenantBooking($tenant, [], 1);
        $this->assertTrue($result['can_book']);
    }

    public function test_booking_helper_get_booking_rules()
    {
        $nextWeekDate = now()->addWeek()->startOfWeek()->addDays(2)->format('Y-m-d');
        $rules = BookingHelper::getBookingRules($nextWeekDate);

        $this->assertArrayHasKey('date', $rules);
        $this->assertArrayHasKey('can_book_free', $rules);
        $this->assertArrayHasKey('can_book_premium', $rules);
        $this->assertArrayHasKey('booking_type', $rules);
        $this->assertArrayHasKey('is_premium_booking_open', $rules);

        $this->assertEquals($nextWeekDate, $rules['date']);
        $this->assertTrue($rules['can_book_free']);
        $this->assertFalse($rules['can_book_premium']);
        $this->assertEquals('free', $rules['booking_type']);
    }

    public function test_booking_helper_get_tenant_quota_info()
    {
        $tenant = Tenant::factory()->create(['booking_limit' => 3]);
        $quotaInfo = BookingHelper::getTenantQuotaInfo($tenant);

        $this->assertArrayHasKey('combined_quota', $quotaInfo);
        $this->assertArrayHasKey('free_quota', $quotaInfo);
        $this->assertArrayHasKey('premium_quota', $quotaInfo);
        $this->assertArrayHasKey('weekly_remaining', $quotaInfo);
        $this->assertArrayHasKey('current_week_usage', $quotaInfo);
    }
}
