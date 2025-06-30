<?php

use App\Models\User;
use App\Settings\PremiumSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
});

test('admin can access settings pages', function () {
    $response = $this->actingAs($this->admin, 'admin')
        ->get('/admin/settings/profile');

    $response->assertSuccessful();
    $response->assertSee('Settings');
});

test('admin can update profile settings', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->call('updateProfile')
        ->assertSessionHas('status');

    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ]);
});

test('admin can update password', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.password')
        ->set('current_password', 'password')
        ->set('password', 'newpassword123')
        ->set('password_confirmation', 'newpassword123')
        ->call('updatePassword')
        ->assertSessionHas('status');

    $this->admin->refresh();
    expect(Hash::check('newpassword123', $this->admin->password))->toBeTrue();
});

test('admin cannot update password with wrong current password', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.password')
        ->set('current_password', 'wrongpassword')
        ->set('password', 'newpassword123')
        ->set('password_confirmation', 'newpassword123')
        ->call('updatePassword')
        ->assertSessionHasErrors(['current_password']);
});

test('admin can update premium booking settings', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.premium-booking')
        ->set('premiumAdvanceDays', 30)
        ->set('freeAdvanceDays', 7)
        ->set('defaultBookingLimit', 5)
        ->call('updatePremiumSettings')
        ->assertSessionHas('status');

    $settings = app(PremiumSettings::class);
    expect($settings->premium_advance_days)->toBe(30);
    expect($settings->free_advance_days)->toBe(7);
    expect($settings->default_booking_limit)->toBe(5);
});

test('admin can update site settings', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.site')
        ->set('siteName', 'Updated Site Name')
        ->set('siteDescription', 'Updated description')
        ->set('contactEmail', 'contact@example.com')
        ->call('updateSiteSettings')
        ->assertSessionHas('status');
});

test('admin can update tenant settings', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.tenants')
        ->set('defaultBookingLimit', 4)
        ->set('allowSelfRegistration', true)
        ->set('requireEmailVerification', true)
        ->call('updateTenantSettings')
        ->assertSessionHas('status');
});

test('settings validation works correctly', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('email', 'invalid-email')
        ->call('updateProfile')
        ->assertSessionHasErrors(['email']);
});

test('settings can handle empty values', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('name', '')
        ->set('email', '')
        ->call('updateProfile')
        ->assertSessionHasErrors(['name', 'email']);
});

test('settings can handle special characters', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('name', 'John Doe & Associates')
        ->set('email', 'john.doe+test@example.com')
        ->call('updateProfile')
        ->assertSessionHas('status');
});

test('settings can handle unicode characters', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('name', 'José María García')
        ->set('email', 'jose@example.com')
        ->call('updateProfile')
        ->assertSessionHas('status');
});

test('settings can handle long values', function () {
    $longName = str_repeat('A', 255);
    $longEmail = str_repeat('a', 240).'@example.com';

    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('name', $longName)
        ->set('email', $longEmail)
        ->call('updateProfile')
        ->assertSessionHas('status');
});

test('settings can handle numeric values', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.premium-booking')
        ->set('premiumAdvanceDays', 30)
        ->set('freeAdvanceDays', 7)
        ->set('defaultBookingLimit', 5)
        ->call('updatePremiumSettings')
        ->assertSessionHas('status');
});

test('settings can handle boolean values', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.tenants')
        ->set('allowSelfRegistration', true)
        ->set('requireEmailVerification', false)
        ->call('updateTenantSettings')
        ->assertSessionHas('status');
});

test('settings can handle array values', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.site')
        ->set('operatingHours', [
            'monday' => '08:00-22:00',
            'tuesday' => '08:00-22:00',
            'wednesday' => '08:00-22:00',
            'thursday' => '08:00-22:00',
            'friday' => '08:00-22:00',
            'saturday' => '09:00-21:00',
            'sunday' => '09:00-21:00',
        ])
        ->call('updateSiteSettings')
        ->assertSessionHas('status');
});

test('settings can be reset to defaults', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.premium-booking')
        ->call('resetToDefaults')
        ->assertSessionHas('status');

    $settings = app(PremiumSettings::class);
    expect($settings->premium_advance_days)->toBe(30); // Default value
    expect($settings->free_advance_days)->toBe(7); // Default value
});

test('settings can be exported', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.site')
        ->call('exportSettings')
        ->assertFileDownloaded('settings.json');
});

test('settings can be imported', function () {
    $settingsData = [
        'site_name' => 'Imported Site',
        'site_description' => 'Imported description',
        'contact_email' => 'imported@example.com',
    ];

    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.site')
        ->set('importFile', $settingsData)
        ->call('importSettings')
        ->assertSessionHas('status');
});

test('settings can handle concurrent updates', function () {
    // Simulate concurrent updates
    $admin1 = User::factory()->create();
    $admin2 = User::factory()->create();

    Volt::actingAs($admin1, 'admin')
        ->test('admin.settings.profile')
        ->set('name', 'Admin 1 Update')
        ->call('updateProfile');

    Volt::actingAs($admin2, 'admin')
        ->test('admin.settings.profile')
        ->set('name', 'Admin 2 Update')
        ->call('updateProfile');

    // Both updates should be successful
    $this->assertDatabaseHas('users', [
        'id' => $admin1->id,
        'name' => 'Admin 1 Update',
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $admin2->id,
        'name' => 'Admin 2 Update',
    ]);
});

test('settings can handle validation errors gracefully', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.premium-booking')
        ->set('premiumAdvanceDays', -1) // Invalid value
        ->set('freeAdvanceDays', 1000) // Too high
        ->call('updatePremiumSettings')
        ->assertSessionHasErrors(['premiumAdvanceDays', 'freeAdvanceDays']);
});

test('settings can handle database errors gracefully', function () {
    // Mock database error
    $this->mock('db')->shouldReceive('transaction')->andThrow(new \Exception('Database error'));

    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('name', 'Test Name')
        ->call('updateProfile')
        ->assertSessionHas('error');
});

test('settings can handle file upload errors', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('profilePicture', 'invalid-file')
        ->call('updateProfile')
        ->assertSessionHasErrors(['profilePicture']);
});

test('settings can handle large file uploads', function () {
    // Create a large file (simulated)
    $largeFile = str_repeat('A', 1024 * 1024); // 1MB

    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('profilePicture', $largeFile)
        ->call('updateProfile')
        ->assertSessionHasErrors(['profilePicture']);
});

test('settings can handle invalid file types', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('profilePicture', 'test.exe') // Invalid file type
        ->call('updateProfile')
        ->assertSessionHasErrors(['profilePicture']);
});

test('settings can handle missing required fields', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('name', null)
        ->set('email', null)
        ->call('updateProfile')
        ->assertSessionHasErrors(['name', 'email']);
});

test('settings can handle duplicate email addresses', function () {
    $otherUser = User::factory()->create(['email' => 'other@example.com']);

    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile')
        ->set('email', 'other@example.com')
        ->call('updateProfile')
        ->assertSessionHasErrors(['email']);
});

test('settings can handle password complexity requirements', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.password')
        ->set('current_password', 'password')
        ->set('password', '123') // Too simple
        ->set('password_confirmation', '123')
        ->call('updatePassword')
        ->assertSessionHasErrors(['password']);
});

test('settings can handle password confirmation mismatch', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.password')
        ->set('current_password', 'password')
        ->set('password', 'newpassword123')
        ->set('password_confirmation', 'differentpassword')
        ->call('updatePassword')
        ->assertSessionHasErrors(['password']);
});

test('settings can handle session timeout', function () {
    Volt::actingAs($this->admin, 'admin')
        ->test('admin.settings.profile');

    // Simulate session timeout
    $this->app['session']->flush();

    $response = $this->get('/admin/settings/profile');
    $response->assertRedirect('/login');
});

test('settings can handle CSRF protection', function () {
    $response = $this->post('/admin/settings/profile', [
        'name' => 'Test Name',
        'email' => 'test@example.com',
    ]);

    $response->assertStatus(419); // CSRF token mismatch
});

test('settings can handle rate limiting', function () {
    // Make multiple rapid requests
    for ($i = 0; $i < 10; $i++) {
        Volt::actingAs($this->admin, 'admin')
            ->test('admin.settings.profile')
            ->set('name', "Test {$i}")
            ->call('updateProfile');
    }

    // The last request should be rate limited
    $response = $this->actingAs($this->admin, 'admin')
        ->post('/admin/settings/profile', [
            'name' => 'Rate Limited',
            'email' => 'rate@example.com',
        ]);

    $response->assertStatus(429); // Too Many Requests
});
