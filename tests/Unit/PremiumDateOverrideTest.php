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
