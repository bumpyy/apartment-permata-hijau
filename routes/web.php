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

Route::redirect('admin/settings', 'admin/settings/profile');
Route::redirect('admin', 'admin/dashboard');

Route::middleware(['auth:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Volt::route('dashboard', 'admin.dashboard')->name('dashboard');

        Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
        Volt::route('settings/password', 'settings.password')->name('settings.password');
        Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    });

require __DIR__.'/auth.php';
