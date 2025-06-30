<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('tenant can register with valid information', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '081234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect('/dashboard');

    $this->assertDatabaseHas('tenants', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '081234567890',
    ]);

    $tenant = Tenant::where('email', 'john@example.com')->first();
    expect($tenant->tenant_id)->toStartWith('tenant#');
    expect($tenant->booking_limit)->toBe(3); // Default limit
    expect($tenant->is_active)->toBeTrue();
});

test('tenant cannot register with invalid email', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'invalid-email',
        'phone' => '081234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertDatabaseMissing('tenants', ['email' => 'invalid-email']);
});

test('tenant cannot register with duplicate email', function () {
    Tenant::factory()->create(['email' => 'john@example.com']);

    $response = $this->post('/register', [
        'name' => 'Jane Doe',
        'email' => 'john@example.com',
        'phone' => '081234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('tenant cannot register with weak password', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '081234567890',
        'password' => '123',
        'password_confirmation' => '123',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('tenant cannot register with mismatched passwords', function () {
    $response = $this->post('/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '081234567890',
        'password' => 'password123',
        'password_confirmation' => 'differentpassword',
    ]);

    $response->assertSessionHasErrors(['password']);
});

test('tenant can login with valid credentials', function () {
    $tenant = Tenant::factory()->create([
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticated('tenant');
});

test('tenant cannot login with invalid credentials', function () {
    $tenant = Tenant::factory()->create([
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => 'john@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest('tenant');
});

test('tenant cannot login with non-existent email', function () {
    $response = $this->post('/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest('tenant');
});

test('tenant cannot login when account is deactivated', function () {
    $tenant = Tenant::factory()->create([
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
        'is_active' => false,
    ]);

    $response = $this->post('/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest('tenant');
});

test('tenant can logout', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant, 'tenant');
    $this->assertAuthenticated('tenant');

    $response = $this->post('/logout');

    $response->assertRedirect('/');
    $this->assertGuest('tenant');
});

test('tenant can access dashboard when authenticated', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($tenant, 'tenant')
        ->get('/dashboard');

    $response->assertSuccessful();
    $response->assertSee('Dashboard');
});

test('tenant cannot access dashboard when not authenticated', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});

test('tenant can request password reset', function () {
    $tenant = Tenant::factory()->create(['email' => 'john@example.com']);

    $response = $this->post('/forgot-password', [
        'email' => 'john@example.com',
    ]);

    $response->assertSessionHas('status');
});

test('tenant cannot request password reset with non-existent email', function () {
    $response = $this->post('/forgot-password', [
        'email' => 'nonexistent@example.com',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('tenant can reset password with valid token', function () {
    $tenant = Tenant::factory()->create(['email' => 'john@example.com']);

    // Simulate password reset token
    $token = 'valid-token';
    $tenant->update(['remember_token' => $token]);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => 'john@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHas('status');

    // Verify password was changed
    $tenant->refresh();
    expect(Hash::check('newpassword123', $tenant->password))->toBeTrue();
});

test('tenant cannot reset password with invalid token', function () {
    $tenant = Tenant::factory()->create(['email' => 'john@example.com']);

    $response = $this->post('/reset-password', [
        'token' => 'invalid-token',
        'email' => 'john@example.com',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors(['email']);
});

test('tenant can update their profile', function () {
    $tenant = Tenant::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $response = $this->actingAs($tenant, 'tenant')
        ->put('/profile', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '081234567890',
        ]);

    $response->assertSessionHas('status');

    $this->assertDatabaseHas('tenants', [
        'id' => $tenant->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '081234567890',
    ]);
});

test('tenant can change their password', function () {
    $tenant = Tenant::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);

    $response = $this->actingAs($tenant, 'tenant')
        ->put('/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertSessionHas('status');

    $tenant->refresh();
    expect(Hash::check('newpassword123', $tenant->password))->toBeTrue();
});

test('tenant cannot change password with wrong current password', function () {
    $tenant = Tenant::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);

    $response = $this->actingAs($tenant, 'tenant')
        ->put('/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

    $response->assertSessionHasErrors(['current_password']);
});

test('tenant can verify their email', function () {
    $tenant = Tenant::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this->actingAs($tenant, 'tenant')
        ->post('/email/verification-notification');

    $response->assertSessionHas('status');
});

test('tenant can access email verification page', function () {
    $tenant = Tenant::factory()->create([
        'email_verified_at' => null,
    ]);

    $response = $this->actingAs($tenant, 'tenant')
        ->get('/verify-email');

    $response->assertSuccessful();
    $response->assertSee('Verify Email');
});

test('tenant can confirm their password', function () {
    $tenant = Tenant::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    $response = $this->actingAs($tenant, 'tenant')
        ->post('/user/confirm-password', [
            'password' => 'password123',
        ]);

    $response->assertSessionHas('status');
});

test('tenant cannot confirm password with wrong password', function () {
    $tenant = Tenant::factory()->create([
        'password' => Hash::make('password123'),
    ]);

    $response = $this->actingAs($tenant, 'tenant')
        ->post('/user/confirm-password', [
            'password' => 'wrongpassword',
        ]);

    $response->assertSessionHasErrors(['password']);
});

test('tenant can access password confirmation page', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($tenant, 'tenant')
        ->get('/user/confirm-password');

    $response->assertSuccessful();
    $response->assertSee('Confirm Password');
});

test('tenant session timeout works correctly', function () {
    $tenant = Tenant::factory()->create();

    $this->actingAs($tenant, 'tenant');
    $this->assertAuthenticated('tenant');

    // Simulate session timeout
    $this->app['session']->flush();

    $response = $this->get('/dashboard');
    $response->assertRedirect('/login');
});

test('tenant can access facilities page when authenticated', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($tenant, 'tenant')
        ->get('/facilities');

    $response->assertSuccessful();
});

test('tenant can access court booking page when authenticated', function () {
    $tenant = Tenant::factory()->create();
    $court = \App\Models\Court::factory()->create();

    $response = $this->actingAs($tenant, 'tenant')
        ->get("/facilities/tennis/court/{$court->id}");

    $response->assertSuccessful();
});

test('tenant cannot access admin pages', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($tenant, 'tenant')
        ->get('/admin/dashboard');

    $response->assertStatus(403);
});

test('tenant can access their own profile', function () {
    $tenant = Tenant::factory()->create();

    $response = $this->actingAs($tenant, 'tenant')
        ->get('/profile');

    $response->assertSuccessful();
    $response->assertSee($tenant->name);
});

test('tenant cannot access other tenant profiles', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();

    $response = $this->actingAs($tenant1, 'tenant')
        ->get("/profile/{$tenant2->id}");

    $response->assertStatus(403);
});
