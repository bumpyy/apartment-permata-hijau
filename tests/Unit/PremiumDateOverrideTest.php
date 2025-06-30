<?php

use App\Models\PremiumDateOverride;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2025-06-01 12:00:00'));
});

test('premium date override can be created', function () {
    $override = PremiumDateOverride::create([
        'date' => '2025-06-15',
        'note' => 'Special event',
    ]);

    expect($override->date)->toBe('2025-06-15');
    expect($override->note)->toBe('Special event');
});

test('premium date override can be found by date', function () {
    $override = PremiumDateOverride::create([
        'date' => '2025-06-15',
        'note' => 'Special event',
    ]);

    $found = PremiumDateOverride::where('date', '2025-06-15')->first();

    expect($found->id)->toBe($override->id);
    expect($found->note)->toBe('Special event');
});

test('premium date override can be updated', function () {
    $override = PremiumDateOverride::create([
        'date' => '2025-06-15',
        'note' => 'Original note',
    ]);

    $override->update([
        'note' => 'Updated note',
    ]);

    expect($override->note)->toBe('Updated note');
});

test('premium date override can be deleted', function () {
    $override = PremiumDateOverride::create([
        'date' => '2025-06-15',
        'note' => 'Special event',
    ]);

    $override->delete();

    expect(PremiumDateOverride::find($override->id))->toBeNull();
});

test('premium date override can be queried by date range', function () {
    PremiumDateOverride::create(['date' => '2025-06-10', 'note' => 'Event 1']);
    PremiumDateOverride::create(['date' => '2025-06-15', 'note' => 'Event 2']);
    PremiumDateOverride::create(['date' => '2025-06-20', 'note' => 'Event 3']);

    $overrides = PremiumDateOverride::whereBetween('date', ['2025-06-12', '2025-06-18'])->get();

    expect($overrides)->toHaveCount(1);
    expect($overrides->first()->note)->toBe('Event 2');
});

test('premium date override can be ordered by date', function () {
    PremiumDateOverride::create(['date' => '2025-06-20', 'note' => 'Event 3']);
    PremiumDateOverride::create(['date' => '2025-06-10', 'note' => 'Event 1']);
    PremiumDateOverride::create(['date' => '2025-06-15', 'note' => 'Event 2']);

    $overrides = PremiumDateOverride::orderBy('date')->get();

    expect($overrides[0]->date)->toBe('2025-06-10');
    expect($overrides[1]->date)->toBe('2025-06-15');
    expect($overrides[2]->date)->toBe('2025-06-20');
});

test('premium date override can be searched by note', function () {
    PremiumDateOverride::create([
        'date' => '2025-06-15',
        'note' => 'Tennis tournament',
    ]);

    $overrides = PremiumDateOverride::where('note', 'like', '%tennis%')->get();

    expect($overrides)->toHaveCount(1);
    expect($overrides->first()->note)->toBe('Tennis tournament');
});

test('premium date override can handle multiple overrides for same date', function () {
    PremiumDateOverride::create(['date' => '2025-06-15', 'note' => 'Event 1']);
    PremiumDateOverride::create(['date' => '2025-06-15', 'note' => 'Event 2']);

    $overrides = PremiumDateOverride::where('date', '2025-06-15')->get();

    expect($overrides)->toHaveCount(2);
});

test('premium date override can be validated', function () {
    $override = PremiumDateOverride::create([
        'date' => '2025-06-15',
        'note' => 'Special event',
    ]);

    expect($override->date)->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    expect($override->note)->toBeString();
});

test('premium date override can handle future dates', function () {
    $futureDate = now()->addMonths(2)->format('Y-m-d');

    $override = PremiumDateOverride::create([
        'date' => $futureDate,
        'note' => 'Future event',
    ]);

    expect($override->date)->toBe($futureDate);
});

test('premium date override can handle past dates', function () {
    $pastDate = now()->subMonths(2)->format('Y-m-d');

    $override = PremiumDateOverride::create([
        'date' => $pastDate,
        'note' => 'Past event',
    ]);

    expect($override->date)->toBe($pastDate);
});

test('premium date override can be bulk created', function () {
    $dates = ['2025-06-10', '2025-06-15', '2025-06-20'];

    foreach ($dates as $date) {
        PremiumDateOverride::create([
            'date' => $date,
            'note' => 'Bulk event',
        ]);
    }

    $overrides = PremiumDateOverride::where('note', 'Bulk event')->get();
    expect($overrides)->toHaveCount(3);
});

test('premium date override can be bulk updated', function () {
    PremiumDateOverride::create([
        'date' => '2025-06-15',
        'note' => 'Original note',
    ]);

    PremiumDateOverride::where('note', 'Original note')
        ->update(['note' => 'Updated note']);

    $override = PremiumDateOverride::where('date', '2025-06-15')->first();
    expect($override->note)->toBe('Updated note');
});

test('premium date override can be bulk deleted', function () {
    PremiumDateOverride::create(['date' => '2025-06-10', 'note' => 'Event 1']);
    PremiumDateOverride::create(['date' => '2025-06-15', 'note' => 'Event 2']);
    PremiumDateOverride::create(['date' => '2025-06-20', 'note' => 'Event 3']);

    PremiumDateOverride::where('note', 'like', 'Event%')->delete();

    expect(PremiumDateOverride::count())->toBe(0);
});

test('premium date override can handle edge case dates', function () {
    $edgeDates = [
        '2025-01-01', // New Year
        '2025-12-31', // Year end
        '2025-02-29', // Leap year (if applicable)
    ];

    foreach ($edgeDates as $date) {
        $override = PremiumDateOverride::create([
            'date' => $date,
            'note' => 'Edge case',
        ]);

        expect($override->date)->toBe($date);
    }
});

test('premium date override can be queried by month', function () {
    PremiumDateOverride::create(['date' => '2025-06-10', 'note' => 'June event']);
    PremiumDateOverride::create(['date' => '2025-07-15', 'note' => 'July event']);

    $juneOverrides = PremiumDateOverride::whereMonth('date', 6)->get();
    expect($juneOverrides)->toHaveCount(1);
    expect($juneOverrides->first()->note)->toBe('June event');
});

test('premium date override can be queried by year', function () {
    PremiumDateOverride::create(['date' => '2025-06-15', 'note' => '2025 event']);
    PremiumDateOverride::create(['date' => '2024-06-15', 'note' => '2024 event']);

    $year2025Overrides = PremiumDateOverride::whereYear('date', 2025)->get();
    expect($year2025Overrides)->toHaveCount(1);
    expect($year2025Overrides->first()->note)->toBe('2025 event');
});

test('premium date override can handle null note', function () {
    $override = PremiumDateOverride::create([
        'date' => '2025-06-15',
        'note' => null,
    ]);

    expect($override->note)->toBeNull();
});

test('premium date override can handle empty note', function () {
    $override = PremiumDateOverride::create([
        'date' => '2025-06-15',
        'note' => '',
    ]);

    expect($override->note)->toBe('');
});

test('premium date override can be counted', function () {
    PremiumDateOverride::create(['date' => '2025-06-10', 'note' => 'Event 1']);
    PremiumDateOverride::create(['date' => '2025-06-15', 'note' => 'Event 2']);

    expect(PremiumDateOverride::count())->toBe(2);
});

test('premium date override can be paginated', function () {
    // Create 25 overrides
    for ($i = 1; $i <= 25; $i++) {
        PremiumDateOverride::create([
            'date' => "2025-06-{$i}",
            'note' => "Event {$i}",
        ]);
    }

    $paginated = PremiumDateOverride::paginate(10);

    expect($paginated->count())->toBe(10);
    expect($paginated->total())->toBe(25);
    expect($paginated->lastPage())->toBe(3);
});

test('premium date override getCurrentMonthPremiumDate returns override if exists', function () {
    PremiumDateOverride::create([
        'date' => '2025-06-20',
        'note' => 'June override',
    ]);

    $premiumDate = PremiumDateOverride::getCurrentMonthPremiumDate();

    expect($premiumDate->format('Y-m-d'))->toBe('2025-06-20');
});

test('premium date override getCurrentMonthPremiumDate falls back to 25th', function () {
    // No override exists for current month

    $premiumDate = PremiumDateOverride::getCurrentMonthPremiumDate();

    expect($premiumDate->format('Y-m-d'))->toBe('2025-06-25');
});

test('premium date override can handle different months', function () {
    PremiumDateOverride::create([
        'date' => '2025-07-15',
        'note' => 'July override',
    ]);

    // Set test time to July
    Carbon::setTestNow(Carbon::parse('2025-07-01 12:00:00'));

    $premiumDate = PremiumDateOverride::getCurrentMonthPremiumDate();

    expect($premiumDate->format('Y-m-d'))->toBe('2025-07-15');
});
