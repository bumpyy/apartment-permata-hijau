<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['guest:tenant'])
    ->group(function () {
        Volt::route('login', 'tenant.auth.login')
            ->name('login');

        Volt::route('forgot-password', 'tenant.auth.forgot-password')
            ->name('password.request');

        Volt::route('reset-password/{token}', 'tenant.auth.reset-password')
            ->name('password.reset');
    });

// Route::middleware(['guest:admin'])
//     ->name('admin.')
//     ->prefix('admin')
//     ->group(function () {
//         Volt::route('login', 'admin.auth.login')
//             ->name('login');

//         Volt::route('forgot-password', 'admin.auth.forgot-password')
//             ->name('password.request');

//         Volt::route('reset-password/{token}', 'admin.auth.reset-password')
//             ->name('password.reset');
//     });

// Route::middleware(['auth:tenant'])->group(function () {
//     Volt::route('verify-email', 'auth.verify-email')
//         ->name('verification.notice');

//     Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
//         ->middleware(['signed', 'throttle:6,1'])
//         ->name('verification.verify');

//     Volt::route('confirm-password', 'auth.confirm-password')
//         ->name('password.confirm');
// });

Route::post('logout', App\Livewire\Actions\Logout::class)
    ->name('logout');
