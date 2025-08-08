<?php

use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FacilitiesController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NewsController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', HomeController::class)->name('home');

Route::view('/about', 'about')->name('about');

Route::prefix('news')
    ->name('news.')
    ->group(function () {
        Route::get('/', [NewsController::class, 'index'])->name('index');

        Route::get('/{slug}', [NewsController::class, 'show'])->name('show');
    });

Route::get('/event', [EventController::class, 'index'])->name('event');

Route::get('/committee', CommitteeController::class)->name('committee');

if (app()->environment('local')) {
    Route::prefix('contact')
        ->name('contact.')
        ->group(function () {
            Route::get('/', [ContactController::class, 'index'])->name('index');
            Route::get('/{slug}', [ContactController::class, 'show'])->name('show');
        });
}

Route::name('facilities.')

    ->prefix('facilities')
    ->name('facilities.')
    ->group(function () {
        Route::get('/', [FacilitiesController::class, 'index'])
            ->name('index');
        // Route::redirect('/', 'facilities/tennis')->name('index');

        Route::get('tennis', [FacilitiesController::class, 'tennis'])
            ->name('tennis');

        Route::redirect('tennis/court/', 'facilities/tennis');

        Volt::route('tennis/court/{id}', 'tenant.booking.main')
            ->name('tennis.booking');
    });

Volt::route('dashboard', 'tenant.dashboard')
    ->middleware([
        'auth:tenant',
    ])
    ->name('tenant.dashboard');

require __DIR__.'/auth.php';
// require __DIR__.'/admin.php';
