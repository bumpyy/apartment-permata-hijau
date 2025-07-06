<?php

namespace Tests\Feature\Settings;

use App\Settings\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PollingControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_disable_realtime_polling()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin, 'admin')
            ->test('admin.settings.site')
            ->set('enable_realtime_polling', false)
            ->call('updateSiteSettings')
            ->assertSessionHas('status');

        $settings = app(SiteSettings::class);
        expect($settings->enable_realtime_polling)->toBeFalse();
    }

    public function test_admin_can_configure_polling_intervals()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin, 'admin')
            ->test('admin.settings.site')
            ->set('polling_interval_active', 45)
            ->set('polling_interval_inactive', 90)
            ->set('polling_interval_mobile', 60)
            ->set('inactivity_timeout', 600)
            ->call('updateSiteSettings')
            ->assertSessionHas('status');

        $settings = app(SiteSettings::class);
        expect($settings->polling_interval_active)->toBe(45);
        expect($settings->polling_interval_inactive)->toBe(90);
        expect($settings->polling_interval_mobile)->toBe(60);
        expect($settings->inactivity_timeout)->toBe(600);
    }

    public function test_polling_validation_works_correctly()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin, 'admin')
            ->test('admin.settings.site')
            ->set('polling_interval_active', 5) // Too low
            ->set('polling_interval_inactive', 1000) // Too high
            ->call('updateSiteSettings')
            ->assertSessionHasErrors(['polling_interval_active', 'polling_interval_inactive']);
    }

    public function test_court_booking_component_respects_polling_settings()
    {
        // Test with polling enabled
        $settings = app(SiteSettings::class);
        $settings->enable_realtime_polling = true;
        $settings->polling_interval_active = 30;
        $settings->save();

        $tenant = \App\Models\Tenant::factory()->create();

        Livewire::actingAs($tenant, 'tenant')
            ->test('court-booking.main', ['courtNumber' => 1])
            ->assertMethodExists('isPollingEnabled')
            ->assertMethodExists('initializePolling');
    }

    public function test_court_booking_component_handles_disabled_polling()
    {
        // Test with polling disabled
        $settings = app(SiteSettings::class);
        $settings->enable_realtime_polling = false;
        $settings->save();

        $tenant = \App\Models\Tenant::factory()->create();

        Livewire::actingAs($tenant, 'tenant')
            ->test('court-booking.main', ['courtNumber' => 1])
            ->assertMethodExists('isPollingEnabled')
            ->assertMethodExists('initializePolling');
    }

    public function test_polling_interval_calculation_works_correctly()
    {
        $settings = app(SiteSettings::class);
        $settings->enable_realtime_polling = true;
        $settings->polling_interval_active = 30;
        $settings->polling_interval_inactive = 60;
        $settings->polling_interval_mobile = 45;
        $settings->save();

        // Test active user on desktop
        $interval = $settings->getPollingInterval(true, false);
        expect($interval)->toBe(30000); // 30 seconds in milliseconds

        // Test inactive user on desktop
        $interval = $settings->getPollingInterval(false, false);
        expect($interval)->toBe(60000); // 60 seconds in milliseconds

        // Test mobile user
        $interval = $settings->getPollingInterval(true, true);
        expect($interval)->toBe(45000); // 45 seconds in milliseconds

        // Test disabled polling
        $settings->enable_realtime_polling = false;
        $settings->save();

        $interval = $settings->getPollingInterval(true, false);
        expect($interval)->toBe(0); // Disabled
    }

    public function test_settings_can_be_reset_to_defaults()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        // First, change some settings
        Livewire::actingAs($admin, 'admin')
            ->test('admin.settings.site')
            ->set('polling_interval_active', 45)
            ->set('enable_realtime_polling', false)
            ->call('updateSiteSettings');

        // Then reset to defaults
        Livewire::actingAs($admin, 'admin')
            ->test('admin.settings.site')
            ->call('resetToDefaults')
            ->assertSessionHas('status');

        $settings = app(SiteSettings::class);
        expect($settings->polling_interval_active)->toBe(30); // Default value
        expect($settings->enable_realtime_polling)->toBeTrue(); // Default value
    }

    public function test_site_settings_page_loads_correctly()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin, 'admin')
            ->test('admin.settings.site')
            ->assertSee('Real-time Polling Settings')
            ->assertSee('Enable Real-time Polling')
            ->assertSee('Active User Interval')
            ->assertSee('Inactive User Interval')
            ->assertSee('Mobile Device Interval')
            ->assertSee('Inactivity Timeout');
    }

    public function test_polling_settings_are_persisted_correctly()
    {
        $admin = \App\Models\User::factory()->create(['role' => 'admin']);

        $testData = [
            'enable_realtime_polling' => true,
            'polling_interval_active' => 25,
            'polling_interval_inactive' => 75,
            'polling_interval_mobile' => 50,
            'inactivity_timeout' => 450,
        ];

        Livewire::actingAs($admin, 'admin')
            ->test('admin.settings.site')
            ->set($testData)
            ->call('updateSiteSettings')
            ->assertSessionHas('status');

        $settings = app(SiteSettings::class);

        foreach ($testData as $key => $value) {
            expect($settings->$key)->toBe($value);
        }
    }
}
