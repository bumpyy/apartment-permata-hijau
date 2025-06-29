<?php

use App\Models\PremiumDateOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create a premium date override', function () {
    $override = PremiumDateOverride::create([
        'date' => '2025-07-15',
        'note' => 'Special event',
    ]);

    expect($override->date)->toBe('2025-07-15');
    expect($override->note)->toBe('Special event');
});

test('can retrieve premium date overrides', function () {
    PremiumDateOverride::create(['date' => '2025-07-10']);
    PremiumDateOverride::create(['date' => '2025-07-20', 'note' => 'Holiday']);

    $overrides = PremiumDateOverride::all();
    expect($overrides)->toHaveCount(2);
    expect($overrides[1]->note)->toBe('Holiday');
});

test('getCurrentMonthPremiumDate returns override when exists', function () {
    $currentMonth = now()->month;
    $currentYear = now()->year;

    // Create an override for current month
    $override = PremiumDateOverride::create([
        'date' => "{$currentYear}-{$currentMonth}-15",
        'note' => 'Test override',
    ]);

    $result = PremiumDateOverride::getCurrentMonthPremiumDate();

    expect($result->format('Y-m-d'))->toBe("{$currentYear}-{$currentMonth}-15");
});

test('getCurrentMonthPremiumDate returns fallback when no override exists', function () {
    $currentMonth = now()->month;
    $currentYear = now()->year;

    // Don't create any overrides

    $result = PremiumDateOverride::getCurrentMonthPremiumDate();

    expect($result->format('Y-m-d'))->toBe("{$currentYear}-{$currentMonth}-25");
});

test('getCurrentMonthPremiumDate handles different months correctly', function () {
    // Create override for a different month
    PremiumDateOverride::create([
        'date' => '2025-08-15',
        'note' => 'August override',
    ]);

    $currentMonth = now()->month;
    $currentYear = now()->year;

    $result = PremiumDateOverride::getCurrentMonthPremiumDate();

    // Should return fallback for current month, not the August override
    expect($result->format('Y-m-d'))->toBe("{$currentYear}-{$currentMonth}-25");
});
