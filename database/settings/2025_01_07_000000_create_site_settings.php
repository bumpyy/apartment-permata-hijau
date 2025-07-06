<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

class CreateSiteSettings extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.site_name', config('app.name', 'Court Booking System'));
        $this->migrator->add('site.site_description', 'Professional court booking management system');
        $this->migrator->add('site.contact_email', 'admin@example.com');
        $this->migrator->add('site.contact_phone', '+1234567890');
        $this->migrator->add('site.site_url', config('app.url'));
        // Real-time polling settings
        $this->migrator->add('site.enable_realtime_polling', true);
        $this->migrator->add('site.polling_interval_active', 30);
        $this->migrator->add('site.polling_interval_inactive', 60);
        $this->migrator->add('site.polling_interval_mobile', 45);
        $this->migrator->add('site.inactivity_timeout', 300);
        // Booking system settings
        $this->migrator->add('site.enable_booking_system', true);
        $this->migrator->add('site.max_bookings_per_tenant', 3);
        $this->migrator->add('site.booking_advance_days', 30);
        $this->migrator->add('site.allow_booking_cancellations', true);
        $this->migrator->add('site.cancellation_hours_limit', 24);
        $this->migrator->add('site.enable_cross_court_conflict_detection', true);
        // Notification settings
        $this->migrator->add('site.enable_email_notifications', true);
        $this->migrator->add('site.enable_sms_notifications', false);
        $this->migrator->add('site.enable_push_notifications', false);
        $this->migrator->add('site.notification_timezone', config('app.timezone', 'UTC'));
        // Maintenance settings
        $this->migrator->add('site.maintenance_mode', false);
        $this->migrator->add('site.maintenance_message', 'We are currently performing maintenance. Please check back soon.');
        $this->migrator->add('site.maintenance_allowed_ips', []);
        // Performance settings
        $this->migrator->add('site.enable_caching', true);
        $this->migrator->add('site.cache_duration', 300);
        $this->migrator->add('site.enable_compression', true);
        $this->migrator->add('site.enable_minification', true);
        // Security settings
        $this->migrator->add('site.enable_rate_limiting', true);
        $this->migrator->add('site.rate_limit_requests', 60);
        $this->migrator->add('site.rate_limit_minutes', 1);
        $this->migrator->add('site.enable_csrf_protection', true);
        $this->migrator->add('site.enable_xss_protection', true);
        // Analytics settings
        $this->migrator->add('site.enable_analytics', false);
        $this->migrator->add('site.google_analytics_id', '');
        $this->migrator->add('site.facebook_pixel_id', '');
        // Social media settings
        $this->migrator->add('site.facebook_url', '');
        $this->migrator->add('site.twitter_url', '');
        $this->migrator->add('site.instagram_url', '');
        $this->migrator->add('site.linkedin_url', '');
        // Legal settings
        $this->migrator->add('site.privacy_policy_url', '');
        $this->migrator->add('site.terms_of_service_url', '');
        $this->migrator->add('site.cookie_policy_url', '');
    }

    public function down(): void
    {
        $this->delete('site');
    }
}
