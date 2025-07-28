<?php

use App\Http\Controllers\CommitteeController;
use App\Http\Controllers\FacilitiesController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('/about', 'about')->name('about');

Route::prefix('news')
    ->name('news.')
    ->group(function () {
        Route::get('/', function () {
            return view('news');
        })->name('index');

        Route::get('/{slug}', function ($slug) {
            return view('news-detail', ['slug' => $slug]);
        })->name('detail');
    });

Route::get('/event', function () {
    return view('event');
})->name('event');

Route::get('/committee', CommitteeController::class)->name('committee');

Route::get('/contact', function () {
    return view('contact');
})->name('contact');

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
