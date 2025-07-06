# Admin Polling Control Feature

This document describes the implementation of admin-controlled real-time polling for the court booking system using Spatie Settings.

## 🎯 Overview

The admin can now control real-time polling settings through the admin panel, allowing them to:
- Enable/disable real-time polling system-wide
- Configure polling intervals for different user states
- Optimize performance based on user activity and device type
- Reduce server load during maintenance or high traffic periods

## 🏗️ Architecture

### Components

1. **SiteSettings Class** (`app/Settings/SiteSettings.php`)
   - Centralized settings management using Spatie Settings
   - Polling configuration properties
   - Helper methods for interval calculation

2. **Site Settings Page** (`resources/views/livewire/admin/settings/site.blade.php`)
   - Admin interface for managing polling settings
   - Real-time validation and feedback
   - Comprehensive settings management

3. **Court Booking Component** (`resources/views/livewire/court-booking/main.blade.php`)
   - Dynamic polling based on site settings
   - Fallback mechanisms for settings unavailability
   - User activity detection and optimization

## ⚙️ Configuration Options

### Real-time Polling Settings

| Setting | Type | Default | Range | Description |
|---------|------|---------|-------|-------------|
| `enable_realtime_polling` | boolean | `true` | - | Master switch for polling system |
| `polling_interval_active` | integer | `30` | 10-300 | Seconds between polls for active users |
| `polling_interval_inactive` | integer | `60` | 30-600 | Seconds between polls for inactive users |
| `polling_interval_mobile` | integer | `45` | 15-300 | Seconds between polls on mobile devices |
| `inactivity_timeout` | integer | `300` | 60-1800 | Seconds before user is considered inactive |

### Additional Site Settings

The system also includes comprehensive site management settings:

- **Site Information**: Name, description, contact details
- **Booking System**: Limits, advance days, cancellation rules
- **Performance**: Caching, compression, minification
- **Security**: Rate limiting, CSRF protection, XSS protection
- **Analytics**: Google Analytics, Facebook Pixel
- **Social Media**: Platform URLs
- **Legal**: Privacy policy, terms of service URLs

## 🔧 Implementation Details

### SiteSettings Class

```php
class SiteSettings extends Settings
{
    // Real-time polling properties
    public bool $enable_realtime_polling;
    public int $polling_interval_active;
    public int $polling_interval_inactive;
    public int $polling_interval_mobile;
    public int $inactivity_timeout;

    // Helper methods
    public function getPollingInterval(bool $isActive = true, bool $isMobile = false): int
    public function isPollingEnabled(): bool
    public function getInactivityTimeout(): int
}
```

### Dynamic Polling Initialization

```php
public function initializePolling()
{
    try {
        $this->siteSettings = app(\App\Settings\SiteSettings::class);

        if ($this->siteSettings->isPollingEnabled()) {
            // Detect device type and set appropriate interval
            $isMobile = $this->detectMobileDevice();
            $this->pollingInterval = $this->siteSettings->getPollingInterval(true, $isMobile);

            // Dispatch event to start polling
            $this->dispatch('start-polling', [
                'interval' => $this->pollingInterval,
                'inactivity_timeout' => $this->siteSettings->getInactivityTimeout()
            ]);
        } else {
            // Polling disabled
            $this->dispatch('stop-polling');
        }
    } catch (\Exception $e) {
        // Fallback to default polling
        $this->initializeDefaultPolling();
    }
}
```

### JavaScript Polling Management

```javascript
// Dynamic polling system based on site settings
let pollingInterval;
let isPollingEnabled = true;

// Listen for Livewire events
$wire.$on('start-polling', (data) => {
    isPollingEnabled = true;
    startPolling(data.interval);
});

$wire.$on('stop-polling', () => {
    isPollingEnabled = false;
    stopPolling();
});

// User activity detection
function setActivePolling() {
    if (isPollingEnabled) {
        updatePollingInterval(30000); // 30 seconds when active
    }
}
```

## 🎨 User Interface

### Admin Settings Page

The site settings page provides:

1. **Real-time Polling Section**
   - Master enable/disable toggle
   - Interval configuration for different user states
   - Real-time validation and feedback
   - Visual indicators for current settings

2. **Configuration Preview**
   - Shows current polling intervals
   - Explains the impact of each setting
   - Provides recommendations for optimal performance

3. **Validation**
   - Ensures intervals are within acceptable ranges
   - Prevents configuration that could impact performance
   - Provides clear error messages

### User Experience Indicators

- **Live Status**: Green dot when polling is active
- **Manual Status**: Gray dot when polling is disabled
- **Updating Status**: Spinning indicator during refresh
- **Last Refresh Time**: Shows when data was last updated

## 📊 Performance Impact

### With Polling Enabled
- **Active Users**: 30-second intervals (120 requests/hour)
- **Inactive Users**: 60-second intervals (60 requests/hour)
- **Mobile Users**: 45-second intervals (80 requests/hour)

### With Polling Disabled
- **Zero automatic requests**
- **Manual refresh only**
- **Significant server load reduction**

### Optimization Features

1. **Activity Detection**: Reduces polling for inactive users
2. **Mobile Optimization**: Longer intervals on mobile devices
3. **Page Visibility**: Pauses polling when tab is hidden
4. **Connection Management**: Automatic reconnection handling

## 🧪 Testing

Comprehensive test coverage includes:

```php
class PollingControlTest extends TestCase
{
    public function test_admin_can_enable_disable_realtime_polling()
    public function test_admin_can_configure_polling_intervals()
    public function test_polling_validation_works_correctly()
    public function test_court_booking_component_respects_polling_settings()
    public function test_polling_interval_calculation_works_correctly()
    public function test_settings_can_be_reset_to_defaults()
}
```

## 🚀 Usage Instructions

### For Administrators

1. **Access Settings**
   - Navigate to Admin Panel → Settings → Site Settings
   - Scroll to "Real-time Polling Settings" section

2. **Configure Polling**
   - Toggle "Enable Real-time Polling" on/off
   - Adjust intervals based on your needs:
     - **Active Users**: 10-300 seconds (default: 30)
     - **Inactive Users**: 30-600 seconds (default: 60)
     - **Mobile Devices**: 15-300 seconds (default: 45)
     - **Inactivity Timeout**: 60-1800 seconds (default: 300)

3. **Save Settings**
   - Click "Save Settings" to apply changes
   - Changes take effect immediately for new sessions

### For Developers

1. **Access Settings Programmatically**
   ```php
   $settings = app(\App\Settings\SiteSettings::class);
   $isEnabled = $settings->isPollingEnabled();
   $interval = $settings->getPollingInterval(true, false);
   ```

2. **Check Polling Status**
   ```php
   if ($this->isPollingEnabled()) {
       // Polling is active
   } else {
       // Polling is disabled
   }
   ```

3. **Handle Settings Changes**
   ```php
   // Settings are automatically cached and updated
   // No additional code needed for settings changes
   ```

## 🔒 Security Considerations

1. **Admin Access Only**: Only authenticated admins can modify settings
2. **Validation**: All inputs are validated before saving
3. **Rate Limiting**: Built-in protection against excessive requests
4. **Fallback Mechanisms**: System continues working if settings are unavailable

## 📈 Monitoring and Analytics

### Key Metrics to Track

- **Polling Requests**: Number of automatic refresh requests
- **User Activity**: Active vs inactive user distribution
- **Device Types**: Mobile vs desktop usage patterns
- **Performance Impact**: Server load with different polling settings

### Recommended Monitoring

```php
// Add to your monitoring system
Log::info('Polling activity', [
    'enabled' => $settings->isPollingEnabled(),
    'active_interval' => $settings->polling_interval_active,
    'requests_per_hour' => 3600 / $settings->polling_interval_active,
]);
```

## 🔄 Migration and Deployment

### Database Migration

The settings are automatically created when the migration runs:

```bash
php artisan migrate
```

### Default Values

If settings don't exist, the system uses these defaults:
- `enable_realtime_polling`: `true`
- `polling_interval_active`: `30` seconds
- `polling_interval_inactive`: `60` seconds
- `polling_interval_mobile`: `45` seconds
- `inactivity_timeout`: `300` seconds

## 🎯 Benefits

### For Administrators
- **Full Control**: Enable/disable polling as needed
- **Performance Tuning**: Optimize intervals for your server capacity
- **Maintenance Mode**: Disable polling during maintenance
- **Cost Reduction**: Reduce server load and bandwidth usage

### For Users
- **Real-time Updates**: See booking changes immediately
- **Battery Optimization**: Reduced polling on mobile devices
- **Better Performance**: Optimized intervals based on activity
- **Reliable Service**: Fallback mechanisms ensure system stability

### For Developers
- **Centralized Configuration**: All settings in one place
- **Easy Testing**: Simple to test different configurations
- **Extensible**: Easy to add new polling-related settings
- **Maintainable**: Clean separation of concerns

## 🔮 Future Enhancements

1. **Scheduled Polling**: Different intervals for different times of day
2. **User Preferences**: Allow users to set their own polling preferences
3. **WebSocket Integration**: Real-time updates without polling
4. **Advanced Analytics**: Detailed polling usage statistics
5. **A/B Testing**: Test different polling configurations

This polling control feature provides administrators with powerful tools to optimize the court booking system's performance while maintaining a great user experience.
