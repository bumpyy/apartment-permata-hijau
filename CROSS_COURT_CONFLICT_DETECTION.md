# Cross-Court Conflict Detection Feature

This document describes the implementation of cross-court booking conflict detection for the tennis court booking system.

## 🎯 Overview

The cross-court conflict detection feature prevents tenants from booking multiple courts at the same time. This is important because tenants must be physically present to use the courts, so they cannot realistically use multiple courts simultaneously.

## 🏗️ Architecture

### Components

1. **SiteSettings Class** (`app/Settings/SiteSettings.php`)
   - New setting: `enable_cross_court_conflict_detection`
   - Helper method: `isCrossCourtConflictDetectionEnabled()`

2. **Booking Model** (`app/Models/Booking.php`)
   - New method: `getCrossCourtConflicts()`
   - Checks for overlapping time slots across different courts

3. **Court Booking Component** (`resources/views/livewire/court-booking/main.blade.php`)
   - New method: `checkCrossCourtConflicts()`
   - Cross-court conflict modal UI
   - Integration with slot selection process

4. **Admin Settings** (`resources/views/livewire/admin/settings/site.blade.php`)
   - Toggle to enable/disable the feature
   - Configuration interface

## ⚙️ Configuration

### Admin Panel Settings

The feature can be controlled through the admin panel:

- **Location**: Admin → Settings → Site Settings → Booking System Settings
- **Setting**: "Enable Cross-Court Conflict Detection"
- **Default**: Enabled (true)
- **Description**: Prevents tenants from booking multiple courts at the same time

### Database Migration

A migration has been created to add the setting to existing installations:

```php
// database/migrations/2025_01_08_000000_add_cross_court_conflict_detection_to_site_settings.php
```

## 🔧 Implementation Details

### Conflict Detection Logic

The system checks for overlapping time slots using the following logic:

1. **Exact Overlap**: New booking starts and ends during an existing booking
2. **Start Overlap**: New booking starts during an existing booking
3. **End Overlap**: New booking ends during an existing booking
4. **Complete Containment**: New booking completely contains an existing booking

### Booking Model Method

```php
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
```

### User Experience

When a tenant tries to book a time slot that conflicts with an existing booking on another court:

1. **Conflict Detection**: System checks for overlapping bookings
2. **Modal Display**: Shows a detailed conflict modal with:
   - List of conflicting bookings
   - Court names and times
   - Booking reference numbers
   - Clear explanation of the issue
3. **Prevention**: Prevents the booking from being added to selection
4. **Guidance**: Provides clear instructions on how to resolve the conflict

### Modal Content

The cross-court conflict modal displays:

- **Header**: Clear warning about cross-court booking conflict
- **Conflict Details**: List of existing bookings with:
  - Court name
  - Time range
  - Booking reference
  - Booking status
- **Footer**: Instructions and action button

## 🧪 Testing

Comprehensive tests have been created in `tests/Feature/CrossCourtConflictTest.php`:

- **Default State**: Feature is enabled by default
- **Admin Control**: Admins can enable/disable the feature
- **Conflict Detection**: Proper detection of overlapping bookings
- **Non-Conflicting**: Different times on different courts work fine
- **Disabled State**: When disabled, conflicts are not detected
- **Partial Overlaps**: Detection of partial time overlaps
- **Cancelled Bookings**: Cancelled bookings are excluded from conflicts
- **Data Accuracy**: Conflict details are correctly populated

## 🚀 Usage Examples

### Scenario 1: Tenant has booking on Court 1 at 10:00-11:00

**Action**: Tenant tries to book Court 2 at 10:00-11:00
**Result**: Cross-court conflict modal appears, booking is prevented

### Scenario 2: Tenant has booking on Court 1 at 10:00-12:00

**Action**: Tenant tries to book Court 2 at 11:00-12:00
**Result**: Cross-court conflict modal appears (partial overlap detected)

### Scenario 3: Tenant has booking on Court 1 at 10:00-11:00

**Action**: Tenant tries to book Court 2 at 14:00-15:00
**Result**: No conflict, booking proceeds normally

### Scenario 4: Feature is disabled

**Action**: Tenant tries to book conflicting times on different courts
**Result**: No conflict detection, booking proceeds normally

## 🔄 Migration and Deployment

### For New Installations

The feature is enabled by default in the site settings migration.

### For Existing Installations

Run the migration to add the setting:

```bash
php artisan migrate
```

The setting will be enabled by default. Admins can disable it through the admin panel if needed.

## 🎨 UI/UX Considerations

### Visual Design

- **Color Scheme**: Orange/red gradient for warning appearance
- **Icons**: Tennis ball emoji (🎾) for court-related conflicts
- **Typography**: Clear hierarchy with appropriate sizing
- **Responsive**: Works on both desktop and mobile devices

### User Guidance

- **Clear Messaging**: Explains why the conflict exists
- **Actionable Information**: Shows specific booking details
- **Resolution Path**: Provides clear instructions on how to resolve
- **Consistent Language**: Uses familiar terminology

## 🔒 Security and Performance

### Security

- **Tenant Isolation**: Only checks conflicts for the current tenant
- **Status Filtering**: Excludes cancelled bookings from conflict detection
- **Input Validation**: Proper validation of date and time parameters

### Performance

- **Efficient Queries**: Uses database indexes for optimal performance
- **Caching**: Leverages existing booking data caching
- **Minimal Impact**: Only runs when feature is enabled and tenant is logged in

## 📈 Future Enhancements

Potential improvements for future versions:

1. **Conflict Resolution**: Allow tenants to cancel conflicting bookings directly from the modal
2. **Notification System**: Email/SMS notifications about potential conflicts
3. **Advanced Scheduling**: Suggest alternative times when conflicts are detected
4. **Conflict History**: Track and report on cross-court conflicts for analytics
5. **Flexible Rules**: Allow different conflict rules for different court types or time periods
