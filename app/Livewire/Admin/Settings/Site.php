<?php

namespace App\Livewire\Admin\Settings;

use App\Settings\SiteSettings;
use Livewire\Component;

class Site extends Component
{
    // === SITE INFORMATION ===
    public string $site_name = '';

    public string $site_description = '';

    public string $contact_email = '';

    public string $contact_phone = '';

    public string $site_url = '';

    // === REAL-TIME POLLING SETTINGS ===
    public bool $enable_realtime_polling = true;

    public int $polling_interval_active = 30;

    public int $polling_interval_inactive = 60;

    public int $polling_interval_mobile = 45;

    public int $inactivity_timeout = 300;

    // === BOOKING SYSTEM SETTINGS ===
    public bool $enable_booking_system = true;

    public int $max_bookings_per_tenant = 3;

    public int $booking_advance_days = 30;

    public bool $allow_booking_cancellations = true;

    public int $cancellation_hours_limit = 24;

    public bool $enable_cross_court_conflict_detection = true;

    // === NOTIFICATION SETTINGS ===
    public bool $enable_email_notifications = true;

    public bool $enable_sms_notifications = false;

    public bool $enable_push_notifications = false;

    public string $notification_timezone = 'UTC';

    // === MAINTENANCE SETTINGS ===
    public bool $maintenance_mode = false;

    public string $maintenance_message = '';

    public array $maintenance_allowed_ips = [];

    // === PERFORMANCE SETTINGS ===
    public bool $enable_caching = true;

    public int $cache_duration = 300;

    public bool $enable_compression = true;

    public bool $enable_minification = true;

    // === SECURITY SETTINGS ===
    public bool $enable_rate_limiting = true;

    public int $rate_limit_requests = 60;

    public int $rate_limit_minutes = 1;

    public bool $enable_csrf_protection = true;

    public bool $enable_xss_protection = true;

    // === ANALYTICS SETTINGS ===
    public bool $enable_analytics = false;

    public string $google_analytics_id = '';

    public string $facebook_pixel_id = '';

    // === SOCIAL MEDIA SETTINGS ===
    public string $facebook_url = '';

    public string $twitter_url = '';

    public string $instagram_url = '';

    public string $linkedin_url = '';

    // === LEGAL SETTINGS ===
    public string $privacy_policy_url = '';

    public string $terms_of_service_url = '';

    public string $cookie_policy_url = '';

    public function mount()
    {
        $settings = app(SiteSettings::class);

        // Load current settings
        $this->site_name = $settings->site_name;
        $this->site_description = $settings->site_description;
        $this->contact_email = $settings->contact_email;
        $this->contact_phone = $settings->contact_phone;
        $this->site_url = $settings->site_url;

        // Real-time polling settings
        $this->enable_realtime_polling = $settings->enable_realtime_polling;
        $this->polling_interval_active = $settings->polling_interval_active;
        $this->polling_interval_inactive = $settings->polling_interval_inactive;
        $this->polling_interval_mobile = $settings->polling_interval_mobile;
        $this->inactivity_timeout = $settings->inactivity_timeout;

        // Booking system settings
        $this->enable_booking_system = $settings->enable_booking_system;
        $this->max_bookings_per_tenant = $settings->max_bookings_per_tenant;
        $this->booking_advance_days = $settings->booking_advance_days;
        $this->allow_booking_cancellations = $settings->allow_booking_cancellations;
        $this->cancellation_hours_limit = $settings->cancellation_hours_limit;
        $this->enable_cross_court_conflict_detection = $settings->enable_cross_court_conflict_detection;

        // Notification settings
        $this->enable_email_notifications = $settings->enable_email_notifications;
        $this->enable_sms_notifications = $settings->enable_sms_notifications;
        $this->enable_push_notifications = $settings->enable_push_notifications;
        $this->notification_timezone = $settings->notification_timezone;

        // Maintenance settings
        $this->maintenance_mode = $settings->maintenance_mode;
        $this->maintenance_message = $settings->maintenance_message;
        $this->maintenance_allowed_ips = $settings->maintenance_allowed_ips;

        // Performance settings
        $this->enable_caching = $settings->enable_caching;
        $this->cache_duration = $settings->cache_duration;
        $this->enable_compression = $settings->enable_compression;
        $this->enable_minification = $settings->enable_minification;

        // Security settings
        $this->enable_rate_limiting = $settings->enable_rate_limiting;
        $this->rate_limit_requests = $settings->rate_limit_requests;
        $this->rate_limit_minutes = $settings->rate_limit_minutes;
        $this->enable_csrf_protection = $settings->enable_csrf_protection;
        $this->enable_xss_protection = $settings->enable_xss_protection;

        // Analytics settings
        $this->enable_analytics = $settings->enable_analytics;
        $this->google_analytics_id = $settings->google_analytics_id;
        $this->facebook_pixel_id = $settings->facebook_pixel_id;

        // Social media settings
        $this->facebook_url = $settings->facebook_url;
        $this->twitter_url = $settings->twitter_url;
        $this->instagram_url = $settings->instagram_url;
        $this->linkedin_url = $settings->linkedin_url;

        // Legal settings
        $this->privacy_policy_url = $settings->privacy_policy_url;
        $this->terms_of_service_url = $settings->terms_of_service_url;
        $this->cookie_policy_url = $settings->cookie_policy_url;
    }

    public function updateSiteSettings()
    {
        $this->validate([
            'site_name' => 'required|string|max:255',
            'site_description' => 'nullable|string|max:500',
            'contact_email' => 'required|email',
            'contact_phone' => 'nullable|string|max:20',
            'site_url' => 'required|url',

            // Real-time polling validation
            'polling_interval_active' => 'required|integer|min:10|max:300',
            'polling_interval_inactive' => 'required|integer|min:30|max:600',
            'polling_interval_mobile' => 'required|integer|min:15|max:300',
            'inactivity_timeout' => 'required|integer|min:60|max:1800',

            // Booking system validation
            'max_bookings_per_tenant' => 'required|integer|min:1|max:10',
            'booking_advance_days' => 'required|integer|min:1|max:365',
            'cancellation_hours_limit' => 'required|integer|min:1|max:168',

            // Performance validation
            'cache_duration' => 'required|integer|min:60|max:3600',
            'rate_limit_requests' => 'required|integer|min:10|max:1000',
            'rate_limit_minutes' => 'required|integer|min:1|max:60',
        ]);

        $settings = app(SiteSettings::class);

        // Update settings
        $settings->site_name = $this->site_name;
        $settings->site_description = $this->site_description;
        $settings->contact_email = $this->contact_email;
        $settings->contact_phone = $this->contact_phone;
        $settings->site_url = $this->site_url;

        // Real-time polling settings
        $settings->enable_realtime_polling = $this->enable_realtime_polling;
        $settings->polling_interval_active = $this->polling_interval_active;
        $settings->polling_interval_inactive = $this->polling_interval_inactive;
        $settings->polling_interval_mobile = $this->polling_interval_mobile;
        $settings->inactivity_timeout = $this->inactivity_timeout;

        // Booking system settings
        $settings->enable_booking_system = $this->enable_booking_system;
        $settings->max_bookings_per_tenant = $this->max_bookings_per_tenant;
        $settings->booking_advance_days = $this->booking_advance_days;
        $settings->allow_booking_cancellations = $this->allow_booking_cancellations;
        $settings->cancellation_hours_limit = $this->cancellation_hours_limit;
        $settings->enable_cross_court_conflict_detection = $this->enable_cross_court_conflict_detection;

        // Notification settings
        $settings->enable_email_notifications = $this->enable_email_notifications;
        $settings->enable_sms_notifications = $this->enable_sms_notifications;
        $settings->enable_push_notifications = $this->enable_push_notifications;
        $settings->notification_timezone = $this->notification_timezone;

        // Maintenance settings
        $settings->maintenance_mode = $this->maintenance_mode;
        $settings->maintenance_message = $this->maintenance_message;
        $settings->maintenance_allowed_ips = $this->maintenance_allowed_ips;

        // Performance settings
        $settings->enable_caching = $this->enable_caching;
        $settings->cache_duration = $this->cache_duration;
        $settings->enable_compression = $this->enable_compression;
        $settings->enable_minification = $this->enable_minification;

        // Security settings
        $settings->enable_rate_limiting = $this->enable_rate_limiting;
        $settings->rate_limit_requests = $this->rate_limit_requests;
        $settings->rate_limit_minutes = $this->rate_limit_minutes;
        $settings->enable_csrf_protection = $this->enable_csrf_protection;
        $settings->enable_xss_protection = $this->enable_xss_protection;

        // Analytics settings
        $settings->enable_analytics = $this->enable_analytics;
        $settings->google_analytics_id = $this->google_analytics_id;
        $settings->facebook_pixel_id = $this->facebook_pixel_id;

        // Social media settings
        $settings->facebook_url = $this->facebook_url;
        $settings->twitter_url = $this->twitter_url;
        $settings->instagram_url = $this->instagram_url;
        $settings->linkedin_url = $this->linkedin_url;

        // Legal settings
        $settings->privacy_policy_url = $this->privacy_policy_url;
        $settings->terms_of_service_url = $this->terms_of_service_url;
        $settings->cookie_policy_url = $this->cookie_policy_url;

        $settings->save();

        session()->flash('status', 'Site settings updated successfully!');
    }

    public function resetToDefaults()
    {
        $settings = app(SiteSettings::class);
        $settings->reset();

        $this->mount(); // Reload the form with default values

        session()->flash('status', 'Settings reset to defaults successfully!');
    }

    public function render()
    {
        return view('livewire.admin.settings.site');
    }
}
