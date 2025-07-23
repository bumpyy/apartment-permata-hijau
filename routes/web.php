<?php

use App\Http\Controllers\FacilitiesController;
use App\Models\Court;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::name('facilities.')
    ->prefix('facilities')
    ->name('facilities.')
    ->group(function () {
        // Route::get('/', FacilitiesController::class)->name('index');
        Route::redirect('/', 'facilities/tennis')->name('index');

        Route::get('tennis', function () {

            $courts = Court::all();

            return view('facilities', compact('courts'));

        })->name('tennis');

        Route::redirect('tennis/court/', 'facilities/tennis')->name('tennis.courts');

        Volt::route('tennis/court/{id}', 'tenant.booking.main')->name('tennis.booking');
    });

Volt::route('dashboard', 'tenant.dashboard')
    ->middleware([
        'auth:tenant',
    ])
    ->name('tenant.dashboard');

require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
