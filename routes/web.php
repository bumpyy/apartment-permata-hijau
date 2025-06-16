<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Volt::route('dashboard', 'tenant.dashboard')
    ->middleware([
        'auth:tenant',
    ])
    ->name('tenant.dashboard');

Volt::route('dashboard', 'tenant.dashboard')
    ->middleware([
        'auth:tenant',
    ])
    ->name('tenant.dashboard');

Volt::route('facilities', 'court-booking.main')
    ->name('facilities');

Route::middleware(['auth:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::redirect('/settings', '/admin/settings/profile');
        Route::redirect('/', '/admin/dashboard');

        Volt::route('dashboard', 'admin.dashboard')->name('dashboard');

        Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
        Volt::route('settings/password', 'settings.password')->name('settings.password');
        // Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    });

require __DIR__.'/auth.php';
