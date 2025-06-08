<?php

use App\Http\Middleware\RoleCheck;
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

// Route::view('dashboard', 'dashboard')
//     ->middleware([
//         'auth:tenant',
//     ])
//     ->name('dashboard');

Volt::route('facilities', 'court-booking')
    ->name('facilities');

Route::middleware(['auth:admin'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});



require __DIR__ . '/auth.php';
