<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
});

test('court can be created with basic information', function () {
    $court = Court::factory()->create([
        'name' => 'Court 1',
        'description' => 'Main tennis court',
        'hourly_rate' => 100000,
        'light_surcharge' => 50000,
    ]);

    expect($court->name)->toBe('Court 1');
    expect($court->description)->toBe('Main tennis court');
    expect($court->hourly_rate)->toBe(100000);
    expect($court->light_surcharge)->toBe(50000);
    expect($court->is_active)->toBeTrue();
});

test('court can have operating hours', function () {
    $court = Court::factory()->create([
        'operating_hours' => [
            'open' => '08:00',
            'close' => '22:00',
        ],
    ]);

    expect($court->operating_hours['open'])->toBe('08:00');
    expect($court->operating_hours['close'])->toBe('22:00');
});

test('court can check if it is operating at a specific time', function () {
    $court = Court::factory()->create([
        'operating_hours' => [
            'open' => '08:00',
            'close' => '22:00',
        ],
    ]);

    expect($court->isOperatingAt('10:00'))->toBeTrue();
    expect($court->isOperatingAt('20:00'))->toBeTrue();
    expect($court->isOperatingAt('07:00'))->toBeFalse();
    expect($court->isOperatingAt('23:00'))->toBeFalse();
});

test('court is always open when no operating hours are set', function () {
    $court = Court::factory()->create([
        'operating_hours' => null,
    ]);

    expect($court->isOperatingAt('00:00'))->toBeTrue();
    expect($court->isOperatingAt('12:00'))->toBeTrue();
    expect($court->isOperatingAt('23:59'))->toBeTrue();
});

test('court can have default operating hours', function () {
    $court = Court::factory()->create([
        'operating_hours' => [
            'open' => '08:00',
        ],
    ]);

    expect($court->isOperatingAt('08:00'))->toBeTrue();
    expect($court->isOperatingAt('23:00'))->toBeTrue(); // Default close time
});

test('court can have relationships with bookings', function () {
    $court = Court::factory()->create();
    $booking = Booking::factory()->create([
        'court_id' => $court->id,
        'tenant_id' => $this->tenant->id,
    ]);

    expect($court->bookings)->toHaveCount(1);
    expect($court->bookings->first()->id)->toBe($booking->id);
});

test('court can have multiple bookings', function () {
    $court = Court::factory()->create();

    Booking::factory()->count(3)->create([
        'court_id' => $court->id,
        'tenant_id' => $this->tenant->id,
    ]);

    expect($court->bookings)->toHaveCount(3);
});

test('court can be deactivated', function () {
    $court = Court::factory()->create(['is_active' => true]);

    $court->update(['is_active' => false]);

    expect($court->is_active)->toBeFalse();
});

test('court can be reactivated', function () {
    $court = Court::factory()->create(['is_active' => false]);

    $court->update(['is_active' => true]);

    expect($court->is_active)->toBeTrue();
});

test('court can have zero hourly rate', function () {
    $court = Court::factory()->create([
        'hourly_rate' => 0,
        'light_surcharge' => 50000,
    ]);

    expect($court->hourly_rate)->toBe(0);
    expect($court->light_surcharge)->toBe(50000);
});

test('court can have zero light surcharge', function () {
    $court = Court::factory()->create([
        'hourly_rate' => 100000,
        'light_surcharge' => 0,
    ]);

    expect($court->hourly_rate)->toBe(100000);
    expect($court->light_surcharge)->toBe(0);
});

test('court can be queried by active status', function () {
    Court::factory()->create(['is_active' => true]);
    Court::factory()->create(['is_active' => false]);

    expect(Court::where('is_active', true)->count())->toBe(1);
    expect(Court::where('is_active', false)->count())->toBe(1);
});

test('court can be ordered by name', function () {
    Court::factory()->create(['name' => 'Court B']);
    Court::factory()->create(['name' => 'Court A']);

    $courts = Court::orderBy('name')->get();

    expect($courts->first()->name)->toBe('Court A');
    expect($courts->last()->name)->toBe('Court B');
});

test('court can be searched by name', function () {
    Court::factory()->create(['name' => 'Tennis Court 1']);
    Court::factory()->create(['name' => 'Basketball Court']);

    $courts = Court::where('name', 'like', '%Tennis%')->get();

    expect($courts)->toHaveCount(1);
    expect($courts->first()->name)->toBe('Tennis Court 1');
});

test('court can have different price structures', function () {
    $premiumCourt = Court::factory()->create([
        'hourly_rate' => 200000,
        'light_surcharge' => 75000,
    ]);

    $standardCourt = Court::factory()->create([
        'hourly_rate' => 100000,
        'light_surcharge' => 50000,
    ]);

    $freeCourt = Court::factory()->create([
        'hourly_rate' => 0,
        'light_surcharge' => 25000,
    ]);

    expect($premiumCourt->hourly_rate)->toBe(200000);
    expect($standardCourt->hourly_rate)->toBe(100000);
    expect($freeCourt->hourly_rate)->toBe(0);
});

test('court can handle edge case operating hours', function () {
    $court = Court::factory()->create([
        'operating_hours' => [
            'open' => '00:00',
            'close' => '23:59',
        ],
    ]);

    expect($court->isOperatingAt('00:00'))->toBeTrue();
    expect($court->isOperatingAt('23:59'))->toBeTrue();
    expect($court->isOperatingAt('12:00'))->toBeTrue();
});

test('court can have 24-hour operation', function () {
    $court = Court::factory()->create([
        'operating_hours' => [
            'open' => '00:00',
            'close' => '23:59',
        ],
    ]);

    expect($court->isOperatingAt('00:00'))->toBeTrue();
    expect($court->isOperatingAt('12:00'))->toBeTrue();
    expect($court->isOperatingAt('23:59'))->toBeTrue();
});

test('court can be created with minimal data', function () {
    $court = Court::create([
        'name' => 'Court 1',
        'hourly_rate' => 0,
        'light_surcharge' => 50000,
        'is_active' => true,
    ]);

    expect($court->name)->toBe('Court 1');
    expect($court->description)->toBeNull();
    expect($court->hourly_rate)->toBe(0);
    expect($court->light_surcharge)->toBe(50000);
    expect($court->is_active)->toBeTrue();
    expect($court->operating_hours)->toBeNull();
});

test('court can be updated', function () {
    $court = Court::factory()->create([
        'name' => 'Old Name',
        'hourly_rate' => 50000,
    ]);

    $court->update([
        'name' => 'New Name',
        'hourly_rate' => 100000,
    ]);

    expect($court->name)->toBe('New Name');
    expect($court->hourly_rate)->toBe(100000);
});

test('court can be deleted', function () {
    $court = Court::factory()->create();
    $courtId = $court->id;

    $court->delete();

    expect(Court::find($courtId))->toBeNull();
});

test('court deletion cascades to bookings', function () {
    $court = Court::factory()->create();
    $booking = Booking::factory()->create([
        'court_id' => $court->id,
        'tenant_id' => $this->tenant->id,
    ]);

    $court->delete();

    expect(Booking::find($booking->id))->toBeNull();
});

test('court can be filtered by price range', function () {
    Court::factory()->create(['hourly_rate' => 50000]);
    Court::factory()->create(['hourly_rate' => 100000]);
    Court::factory()->create(['hourly_rate' => 150000]);

    $affordableCourts = Court::where('hourly_rate', '<=', 100000)->get();
    expect($affordableCourts)->toHaveCount(2);

    $premiumCourts = Court::where('hourly_rate', '>', 100000)->get();
    expect($premiumCourts)->toHaveCount(1);
});

test('court can be filtered by light surcharge', function () {
    Court::factory()->create(['light_surcharge' => 25000]);
    Court::factory()->create(['light_surcharge' => 50000]);
    Court::factory()->create(['light_surcharge' => 75000]);

    $standardLightCourts = Court::where('light_surcharge', '<=', 50000)->get();
    expect($standardLightCourts)->toHaveCount(2);

    $highLightCourts = Court::where('light_surcharge', '>', 50000)->get();
    expect($highLightCourts)->toHaveCount(1);
});
