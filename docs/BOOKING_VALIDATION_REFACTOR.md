# Booking Validation Refactoring

This document describes the refactoring of booking validation logic to improve reusability and maintainability across the application.

## Overview

The booking validation logic has been refactored from being scattered across multiple components into a centralized, reusable system. This includes:

1. **BookingValidationService** - Core validation logic
2. **HasBookingValidation Trait** - Shared validation methods for components
3. **Enhanced Tenant Model** - Tenant-specific validation methods
4. **BookingHelper** - Helper class for common validation scenarios

## Architecture

### 1. BookingValidationService

Located at `app/Services/BookingValidationService.php`

**Purpose**: Centralized service containing all booking validation logic.

**Key Methods**:
- `validateSlotSelection()` - Validates multiple slot selections
- `canBookFree()` - Checks if free booking is available
- `canBookPremium()` - Checks if premium booking is available
- `canBookSlot()` - Checks if a slot can be booked
- `getDateBookingType()` - Gets booking type for a date
- `isSlotAlreadyBooked()` - Checks if slot is already booked
- `checkCrossCourtConflicts()` - Checks for cross-court conflicts
- `getAvailableTimeSlots()` - Gets available time slots for a date

### 2. HasBookingValidation Trait

Located at `app/Traits/HasBookingValidation.php`

**Purpose**: Provides common validation methods that can be used by any component.

**Usage**: Simply add `use HasBookingValidation;` to any component class.

**Key Methods**:
- `canBookFree()`, `canBookPremium()`, `canBookSlot()`
- `getDateBookingType()`, `getDateBookingInfo()`
- `generateWeekDays()`, `generateMonthDays()`
- `getDateBookingCounts()`, `getAvailableTimeSlots()`

### 3. Enhanced Tenant Model

Located at `app/Models/Tenant.php`

**Purpose**: Tenant-specific validation methods that use the validation service.

**New Methods**:
- `validateSlotSelection()` - Validates slot selection for this tenant
- `canBookSlot()` - Checks if tenant can book a specific slot
- `canBookMultipleSlots()` - Validates multiple slot selections
- `getAvailableTimeSlots()` - Gets available slots for tenant
- `checkCrossCourtConflicts()` - Checks cross-court conflicts for tenant

### 4. BookingHelper

Located at `app/Helpers/BookingHelper.php`

**Purpose**: Helper class demonstrating common validation scenarios.

**Example Methods**:
- `validateTenantBooking()` - Validate tenant booking request
- `canTenantBookSlot()` - Check if tenant can book specific slot
- `getAvailableSlots()` - Get available slots for date/court
- `getBookingRules()` - Get booking rules for a date
- `validateMultipleBookings()` - Validate multiple bookings
- `getTenantQuotaInfo()` - Get tenant quota information

## Usage Examples

### In Components (Using Trait)

```php
use App\Traits\HasBookingValidation;

class MyBookingComponent extends Component
{
    use HasBookingValidation;

    public function someMethod()
    {
        // Check if date can be booked for free
        $canBookFree = $this->canBookFree('2024-01-15');

        // Get booking type for date
        $bookingType = $this->getDateBookingType('2024-01-15');

        // Generate week days
        $weekDays = $this->generateWeekDays(Carbon::parse('2024-01-15'));
    }
}
```

### In Tenant Model

```php
$tenant = Tenant::find(1);

// Validate slot selection
$validationResult = $tenant->validateSlotSelection(['2024-01-15-10:00', '2024-01-15-11:00'], 1);

if ($validationResult['can_book']) {
    // Proceed with booking
} else {
    // Handle validation errors
    $warnings = $validationResult['warnings'];
    $conflicts = $validationResult['conflicts'];
}

// Check if tenant can book specific slot
$canBook = $tenant->canBookSlot(Carbon::parse('2024-01-15'), '10:00', 1);
```

### Using BookingHelper

```php
use App\Helpers\BookingHelper;

// Validate tenant booking
$validationResult = BookingHelper::validateTenantBooking($tenant, $slotKeys, $courtId);

// Get available slots
$availableSlots = BookingHelper::getAvailableSlots($courtId, '2024-01-15');

// Get booking rules
$rules = BookingHelper::getBookingRules('2024-01-15');

// Get tenant quota info
$quotaInfo = BookingHelper::getTenantQuotaInfo($tenant);
```

### Direct Service Usage

```php
use App\Services\BookingValidationService;

$validationService = app(BookingValidationService::class);

// Validate slot selection
$result = $validationService->validateSlotSelection($tenant, $slotKeys, $courtId);

// Check if slot is available
$isAvailable = $validationService->canBookSlot(Carbon::parse('2024-01-15'), '10:00');

// Get available time slots
$slots = $validationService->getAvailableTimeSlots($courtId, Carbon::parse('2024-01-15'));
```

## Migration Guide

### For Existing Components

1. **Add the trait**:
   ```php
   use App\Traits\HasBookingValidation;

   class YourComponent extends Component
   {
       use HasBookingValidation;
   }
   ```

2. **Remove duplicate methods**: Remove any duplicate validation methods that are now provided by the trait.

3. **Update method calls**: Update any method calls to use the trait methods.

### For New Components

1. **Use the trait** for common validation methods
2. **Use the service** for complex validation logic
3. **Use the helper** for common scenarios

## Benefits

1. **Reusability**: Validation logic can be used across multiple components
2. **Maintainability**: Single source of truth for validation rules
3. **Consistency**: Same validation logic everywhere
4. **Testability**: Easier to test validation logic in isolation
5. **Flexibility**: Easy to modify validation rules in one place

## Validation Rules

The refactored system maintains the same validation rules:

1. **Daily Quota**: Maximum 2 hours per day per tenant
2. **Weekly Quota**: Maximum 3 distinct days per week per tenant
3. **Free Booking**: Only available for next week (Monday to Sunday)
4. **Premium Booking**: Currently disabled (returns false)
5. **Cross-Court Conflicts**: Prevents booking multiple courts at same time
6. **Past Dates**: Cannot book past dates/times
7. **Already Booked**: Cannot book already booked slots

## Testing

The validation logic can be tested independently:

```php
// Test service methods
$service = new BookingValidationService();
$result = $service->validateSlotSelection($tenant, $slotKeys, $courtId);

// Test tenant methods
$tenant = Tenant::factory()->create();
$result = $tenant->validateSlotSelection($slotKeys, $courtId);

// Test helper methods
$result = BookingHelper::validateTenantBooking($tenant, $slotKeys, $courtId);
```

## Future Enhancements

1. **Caching**: Add caching for frequently accessed validation results
2. **Events**: Add events for validation failures
3. **Logging**: Add logging for validation decisions
4. **Configuration**: Make validation rules configurable
5. **Performance**: Optimize database queries for validation
