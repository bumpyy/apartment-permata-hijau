<?php

use App\Models\Booking;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tenant can be created with auto-generated tenant_id', function () {
    $tenant = Tenant::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    expect($tenant->tenant_id)->toStartWith('tenant#');
    expect($tenant->name)->toBe('Test User');
    expect($tenant->email)->toBe('test@example.com');
});

test('tenant can be created with explicit tenant_id', function () {
    $tenant = Tenant::create([
        'tenant_id' => 'tenant#164',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    expect($tenant->tenant_id)->toBe('tenant#164');
    expect($tenant->name)->toBe('John Doe');
});

test('tenant factory creates valid tenants', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->tenant_id)->toStartWith('tenant#');
    expect($tenant->name)->not->toBeEmpty();
    expect($tenant->email)->not->toBeEmpty();
    expect($tenant->booking_limit)->toBe(3);
    expect($tenant->is_active)->toBeTrue();
});

test('tenant display name returns tenant_id when available', function () {
    $tenant = Tenant::factory()->create([
        'tenant_id' => 'tenant#164',
        'name' => 'John Doe',
    ]);

    expect($tenant->display_name)->toBe('tenant#164');
});

test('tenant display name returns name when tenant_id is null', function () {
    $tenant = new Tenant([
        'tenant_id' => null,
        'name' => 'John Doe',
    ]);

    expect($tenant->display_name)->toBe('John Doe');
});
