<?php

use App\Settings\SiteSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('components.backend.layouts.app')]
#[Title('Site Settings')]
class extends Component
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
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="space-y-8">
            <!-- Success Message -->
            @if (session('status'))
                <div class="rounded-lg bg-green-50 border border-green-200 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('status') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <form wire:submit="updateSiteSettings" class="space-y-8">
                <!-- Site Information -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Site Information
                        </h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:input wire:model="site_name" label="Site Name" type="text" required />
                            <flux:input wire:model="site_description" label="Site Description" type="text" />
                            <flux:input wire:model="contact_email" label="Contact Email" type="email" required />
                            <flux:input wire:model="contact_phone" label="Contact Phone" type="text" />
                            <flux:input wire:model="site_url" label="Site URL" type="url" required />
                        </div>
                    </div>
                </div>

                <!-- Real-time Polling Settings -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Real-time Polling Settings
                        </h3>
                        <div class="space-y-4">
                            <flux:checkbox wire:model="enable_realtime_polling" label="Enable Real-time Polling" />

                            @if($enable_realtime_polling)
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                    <flux:input wire:model="polling_interval_active" label="Active User Interval (seconds)" type="number" min="10" max="300" />
                                    <flux:input wire:model="polling_interval_inactive" label="Inactive User Interval (seconds)" type="number" min="30" max="600" />
                                    <flux:input wire:model="polling_interval_mobile" label="Mobile Device Interval (seconds)" type="number" min="15" max="300" />
                                    <flux:input wire:model="inactivity_timeout" label="Inactivity Timeout (seconds)" type="number" min="60" max="1800" />
                                </div>

                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-medium text-blue-800">Polling Configuration</h4>
                                            <div class="mt-2 text-sm text-blue-700">
                                                <p><strong>Active Users:</strong> {{ $polling_interval_active }} seconds</p>
                                                <p><strong>Inactive Users:</strong> {{ $polling_interval_inactive }} seconds</p>
                                                <p><strong>Mobile Devices:</strong> {{ $polling_interval_mobile }} seconds</p>
                                                <p><strong>Inactivity Timeout:</strong> {{ $inactivity_timeout }} seconds</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm text-yellow-700">
                                                Real-time polling is disabled. Users will need to manually refresh to see booking updates.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Booking System Settings -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Booking System Settings
                        </h3>
                        <div class="space-y-4">
                            <flux:checkbox wire:model="enable_booking_system" label="Enable Booking System" />

                            @if($enable_booking_system)
                                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <flux:input wire:model="max_bookings_per_tenant" label="Max Bookings per Tenant" type="number" min="1" max="10" />
                                    <flux:input wire:model="booking_advance_days" label="Booking Advance Days" type="number" min="1" max="365" />
                                    <flux:checkbox wire:model="allow_booking_cancellations" label="Allow Booking Cancellations" />
                                    <flux:input wire:model="cancellation_hours_limit" label="Cancellation Hours Limit" type="number" min="1" max="168" />
                                    <flux:checkbox wire:model="enable_cross_court_conflict_detection" label="Enable Cross-Court Conflict Detection" />
                                </div>

                                @if($enable_cross_court_conflict_detection)
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h4 class="text-sm font-medium text-blue-800">Cross-Court Conflict Detection</h4>
                                                <div class="mt-2 text-sm text-blue-700">
                                                    <p>Prevents tenants from booking multiple courts at the same time.</p>
                                                    <p>This helps ensure tenants can only use one court at a time since they must be physically present.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Performance Settings -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Performance Settings
                        </h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:checkbox wire:model="enable_caching" label="Enable Caching" />
                            <flux:input wire:model="cache_duration" label="Cache Duration (seconds)" type="number" min="60" max="3600" />
                            <flux:checkbox wire:model="enable_compression" label="Enable Compression" />
                            <flux:checkbox wire:model="enable_minification" label="Enable Minification" />
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="bg-white shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Security Settings
                        </h3>
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <flux:checkbox wire:model="enable_rate_limiting" label="Enable Rate Limiting" />
                            <flux:input wire:model="rate_limit_requests" label="Rate Limit Requests" type="number" min="10" max="1000" />
                            <flux:input wire:model="rate_limit_minutes" label="Rate Limit Minutes" type="number" min="1" max="60" />
                            <flux:checkbox wire:model="enable_csrf_protection" label="Enable CSRF Protection" />
                            <flux:checkbox wire:model="enable_xss_protection" label="Enable XSS Protection" />
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-between">
                    <flux:button type="button" wire:click="resetToDefaults">
                        Reset to Defaults
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Save Settings
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</section>
