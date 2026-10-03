<?php

/*
| Which price applies on a night (ARCHITECTURE §6.3): override → highest-priority season
| (most specific weekday set) → base rate.
*/
use Carbon\CarbonImmutable;
use Modules\Rates\DTOs\NightlyRate;
use Modules\Rates\DTOs\RateRow;
use Modules\Rates\DTOs\SeasonWindow;
use Modules\Rates\Enums\RateSource;
use Modules\Rates\Services\NightlyRateResolver;
use Modules\Rates\Support\DaysOfWeek;

const EVERY_DAY = 127;

function weekend(): int
{
    return DaysOfWeek::mask([5, 6]); // Friday, Saturday
}

/**
 * @return list<SeasonWindow>
 */
function demoSeasons(): array
{
    return [
        new SeasonWindow(1, 'Peak', 'danger', 30, [['2026-12-15', '2027-01-31']]),
        new SeasonWindow(2, 'Shoulder', 'warning', 10, [['2026-10-01', '2026-11-30'], ['2027-02-01', '2027-03-31']]),
        new SeasonWindow(3, 'Puja holidays', 'info', 40, [['2026-10-18', '2026-10-24']]),
    ];
}

/**
 * @return list<RateRow>
 */
function demoRates(): array
{
    return [
        new RateRow(1, null, EVERY_DAY, '6000.00', '1500.00', '750.00'),
        new RateRow(2, null, weekend(), '7000.00', '1500.00', '750.00'),
        new RateRow(3, 1, EVERY_DAY, '9500.00', '2000.00', '1000.00'),
        new RateRow(4, 1, weekend(), '11000.00', '2000.00', '1000.00'),
        new RateRow(5, 2, EVERY_DAY, '7500.00', '1500.00', '750.00'),
    ];
}

function priceOn(string $date, ?string $override = null): ?NightlyRate
{
    return new NightlyRateResolver()->resolve(CarbonImmutable::parse($date), demoSeasons(), demoRates(), $override);
}

it('uses the base rate outside every season, with the weekend rate on Fri and Sat', function (): void {
    expect(priceOn('2026-07-08')?->amount)->toBe('6000.00') // Wednesday
        ->and(priceOn('2026-07-08')?->source)->toBe(RateSource::Base)
        ->and(priceOn('2026-07-10')?->amount)->toBe('7000.00') // Friday
        ->and(priceOn('2026-07-11')?->amount)->toBe('7000.00') // Saturday
        ->and(priceOn('2026-07-12')?->amount)->toBe('6000.00'); // Sunday
});

it('uses the season rate inside a season, including its weekend rate', function (): void {
    $wednesday = priceOn('2026-12-16');

    expect($wednesday?->amount)->toBe('9500.00')
        ->and($wednesday?->source)->toBe(RateSource::Season)
        ->and($wednesday?->seasonName)->toBe('Peak')
        ->and($wednesday?->extraAdultAmount)->toBe('2000.00')
        ->and(priceOn('2026-12-18')?->amount)->toBe('11000.00'); // Friday
});

it('includes both ends of a season period and covers every period of a season', function (): void {
    expect(priceOn('2026-12-15')?->amount)->toBe('9500.00')
        ->and(priceOn('2027-01-31')?->amount)->toBe('9500.00') // Sunday
        ->and(priceOn('2027-02-01')?->seasonName)->toBe('Shoulder')
        ->and(priceOn('2026-11-30')?->seasonName)->toBe('Shoulder')
        ->and(priceOn('2026-09-30')?->source)->toBe(RateSource::Base);
});

it('falls back to the base rate when the winning season has no rate for the day', function (): void {
    // Shoulder has no weekend rate: its every-day rate applies on Friday.
    expect(priceOn('2026-10-02')?->amount)->toBe('7500.00');

    // Puja (priority 40) beats Shoulder but has no rates at all: the base rate applies, not Shoulder's.
    $puja = priceOn('2026-10-21');
    expect($puja?->amount)->toBe('6000.00')->and($puja?->source)->toBe(RateSource::Base);
});

it('lets the higher priority win where seasons overlap, and the newer season on a tie', function (): void {
    $seasons = [
        new SeasonWindow(1, 'Peak', 'danger', 30, [['2026-12-01', '2026-12-31']]),
        new SeasonWindow(2, 'Christmas', 'success', 50, [['2026-12-24', '2026-12-26']]),
        new SeasonWindow(3, 'Winter', 'info', 30, [['2026-12-20', '2026-12-22']]),
    ];
    $rates = [
        new RateRow(1, 1, EVERY_DAY, '9000.00', '0.00', '0.00'),
        new RateRow(2, 2, EVERY_DAY, '14000.00', '0.00', '0.00'),
        new RateRow(3, 3, EVERY_DAY, '9900.00', '0.00', '0.00'),
    ];
    $resolver = new NightlyRateResolver;

    expect($resolver->resolve(CarbonImmutable::parse('2026-12-25'), $seasons, $rates)?->amount)->toBe('14000.00')
        ->and($resolver->resolve(CarbonImmutable::parse('2026-12-21'), $seasons, $rates)?->amount)->toBe('9900.00')
        ->and($resolver->resolve(CarbonImmutable::parse('2026-12-28'), $seasons, $rates)?->amount)->toBe('9000.00');
});

it('lets a date override beat everything, keeping the extras of the rate below', function (): void {
    $newYear = priceOn('2026-12-31', '15000.00');

    expect($newYear?->amount)->toBe('15000.00')
        ->and($newYear?->source)->toBe(RateSource::Override)
        ->and($newYear?->extraAdultAmount)->toBe('2000.00')
        ->and($newYear?->seasonName)->toBe('Peak');
});

it('has no price without a matching rate, unless there is an override', function (): void {
    $resolver = new NightlyRateResolver;
    $weekdaysOnly = [new RateRow(1, null, DaysOfWeek::mask([1, 2, 3, 4, 7]), '5000.00', '0.00', '0.00')];

    expect($resolver->resolve(CarbonImmutable::parse('2026-07-10'), [], $weekdaysOnly))->toBeNull()
        ->and($resolver->resolve(CarbonImmutable::parse('2026-07-10'), [], []))->toBeNull()
        ->and($resolver->resolve(CarbonImmutable::parse('2026-07-10'), [], [], '8000.00')?->extraAdultAmount)->toBe('0.00');
});
