<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SiteSettings extends Settings
{
    // === SITE INFORMATION ===
    public string $site_name;
    public string $site_description;
    public string $contact_email;
    public string $contact_phone;
    public string $site_url;

    // === REAL-TIME POLLING SETTINGS ===
    public bool $enable_realtime_polling;
    public int $polling_interval_active; // in seconds
    public int $polling_interval_inactive; // in seconds
    public int $polling_interval_mobile; // in seconds
    public int $inactivity_timeout; // in seconds

    // === BOOKING SYSTEM SETTINGS ===
    public bool $enable_booking_system;
    public int $max_bookings_per_tenant;
    public int $booking_advance_days;
    public bool $allow_booking_cancellations;
    public int $cancellation_hours_limit;
    public bool $enable_cross_court_conflict_detection;

    // === NOTIFICATION SETTINGS ===
    public bool $enable_email_notifications;
    public bool $enable_sms_notifications;
    public bool $enable_push_notifications;
    public string $notification_timezone;

    // === MAINTENANCE SETTINGS ===
    public bool $maintenance_mode;
    public string $maintenance_message;
    public array $maintenance_allowed_ips;

    // === PERFORMANCE SETTINGS ===
    public bool $enable_caching;
    public int $cache_duration; // in seconds
    public bool $enable_compression;
    public bool $enable_minification;

    // === SECURITY SETTINGS ===
    public bool $enable_rate_limiting;
    public int $rate_limit_requests;
    public int $rate_limit_minutes;
    public bool $enable_csrf_protection;
    public bool $enable_xss_protection;

    // === ANALYTICS SETTINGS ===
    public bool $enable_analytics;
    public string $google_analytics_id;
    public string $facebook_pixel_id;

    // === SOCIAL MEDIA SETTINGS ===
    public string $facebook_url;
    public string $twitter_url;
    public string $instagram_url;
    public string $linkedin_url;

    // === LEGAL SETTINGS ===
    public string $privacy_policy_url;
    public string $terms_of_service_url;
    public string $cookie_policy_url;

    public static function group(): string
    {
        return 'site';
    }

    public static function defaults(): array
    {
        return [
            // Site Information
            'site_name' => config('app.name', 'Court Booking System'),
            'site_description' => 'Professional court booking management system',
            'contact_email' => 'admin@example.com',
            'contact_phone' => '+1234567890',
            'site_url' => config('app.url'),

            // Real-time Polling Settings
            'enable_realtime_polling' => true,
            'polling_interval_active' => 30, // 30 seconds
            'polling_interval_inactive' => 60, // 1 minute
            'polling_interval_mobile' => 45, // 45 seconds
            'inactivity_timeout' => 300, // 5 minutes

            // Booking System Settings
            'enable_booking_system' => true,
            'max_bookings_per_tenant' => 3,
            'booking_advance_days' => 30,
            'allow_booking_cancellations' => true,
            'cancellation_hours_limit' => 24,
            'enable_cross_court_conflict_detection' => true,

            // Notification Settings
            'enable_email_notifications' => true,
            'enable_sms_notifications' => false,
            'enable_push_notifications' => false,
            'notification_timezone' => config('app.timezone', 'UTC'),

            // Maintenance Settings
            'maintenance_mode' => false,
            'maintenance_message' => 'We are currently performing maintenance. Please check back soon.',
            'maintenance_allowed_ips' => [],

            // Performance Settings
            'enable_caching' => true,
            'cache_duration' => 300, // 5 minutes
            'enable_compression' => true,
            'enable_minification' => true,

            // Security Settings
            'enable_rate_limiting' => true,
            'rate_limit_requests' => 60,
            'rate_limit_minutes' => 1,
            'enable_csrf_protection' => true,
            'enable_xss_protection' => true,

            // Analytics Settings
            'enable_analytics' => false,
            'google_analytics_id' => '',
            'facebook_pixel_id' => '',

            // Social Media Settings
            'facebook_url' => '',
            'twitter_url' => '',
            'instagram_url' => '',
            'linkedin_url' => '',

            // Legal Settings
            'privacy_policy_url' => '',
            'terms_of_service_url' => '',
            'cookie_policy_url' => '',
        ];
    }

    /**
     * Get polling interval based on user activity and device type
     */
    public function getPollingInterval(bool $isActive = true, bool $isMobile = false): int
    {
        if (!$this->enable_realtime_polling) {
            return 0; // Disabled
        }

        if ($isMobile) {
            return $this->polling_interval_mobile * 1000; // Convert to milliseconds
        }

        return ($isActive ? $this->polling_interval_active : $this->polling_interval_inactive) * 1000;
    }

    /**
     * Check if real-time polling is enabled
     */
    public function isPollingEnabled(): bool
    {
        return $this->enable_realtime_polling;
    }

    /**
     * Get inactivity timeout in milliseconds
     */
    public function getInactivityTimeout(): int
    {
        return $this->inactivity_timeout * 1000; // Convert to milliseconds
    }

    /**
     * Check if cross-court conflict detection is enabled
     */
    public function isCrossCourtConflictDetectionEnabled(): bool
    {
        return $this->enable_cross_court_conflict_detection;
    }
}
