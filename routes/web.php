<?php

use App\Http\Controllers\FacilitiesController;
use App\Models\Court;
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

Route::name('facilities.')
    ->prefix('facilities')
    ->name('facilities.')
    ->group(function () {
        Route::get('/', FacilitiesController::class)->name('index');

        Route::get('tennis', function () {

            $courts = Court::all();

            return view('facilities', compact('courts'));

        })->name('tennis');

        Route::redirect('tennis/court/', 'facilities/tennis')->name('tennis.courts');

        Volt::route('tennis/court/{id}', 'court-booking.main')->name('tennis.booking');
    });

Route::middleware(['auth:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::redirect('/settings', '/admin/settings/profile');
        Route::redirect('/', '/admin/dashboard');

        Volt::route('dashboard', 'admin.dashboard')->name('dashboard');
        // Volt::route('calendar', 'admin.calendar')->name('calendar');

        Volt::route('dashboard', 'admin.dashboard')->name('dashboard');

        Volt::route('booking', 'admin.booking')->name('booking.list');
        Volt::route('booking/create', 'admin.booking.create.main')->name('booking.create');

        Volt::route('tenant', 'admin.tenant-list')->name('tenant.list');

        Volt::route('tenant/{id}', 'admin.tenant-details')->name('tenant.show');

        Volt::route('settings/premium', 'admin.settings.premium-booking')->name('settings.premium');
        Volt::route('settings/tenants', 'admin.settings.tenants')->name('settings.tenants');
        Volt::route('settings/site', 'admin.settings.site')->name('settings.site');

        Volt::route('settings/profile', 'admin.settings.profile')->name('settings.profile');
        Volt::route('settings/password', 'admin.settings.password')->name('settings.password');
        // Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    });

require __DIR__.'/auth.php';
