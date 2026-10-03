<?php

use Modules\Rates\DTOs\NightlyRate;
use Modules\Rates\Enums\RateSource;
use Modules\Reservation\DTOs\NightPrice;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\Services\PriceCalculator;

function rateOf(string $date, string $amount, RateSource $source = RateSource::Base): NightlyRate
{
    return new NightlyRate($date, $amount, '1500.00', '750.00', $source);
}

it('charges extra adults and children beyond the base occupancy, adults first', function (array $party, int $base, array $extras): void {
    expect(new PriceCalculator()->extraGuests($base, new Occupancy(...$party)))->toBe($extras);
})->with([
    '2 adults in a room for 2' => [[2, 0], 2, [0, 0]],
    '3 adults' => [[3, 0], 2, [1, 0]],
    '2 adults + 1 child' => [[2, 1], 2, [0, 1]],
    '1 adult + 1 child fill the base' => [[1, 1], 2, [0, 0]],
    '1 adult + 2 children' => [[1, 2], 2, [0, 1]],
    '4 adults + 2 children, base 4' => [[4, 2], 4, [0, 2]],
]);

it('prices each night with extras, and has no price when a night has no rate', function (): void {
    $calculator = new PriceCalculator;
    $nights = $calculator->nights(['2026-12-15' => rateOf('2026-12-15', '9500.00', RateSource::Season), '2026-12-16' => rateOf('2026-12-16', '9500.00')], 2, new Occupancy(3, 1));

    expect(array_map(fn (NightPrice $n): array => [$n->base, $n->extras, $n->source], $nights ?? []))
        ->toBe([['9500.00', '2250.00', 'season'], ['9500.00', '2250.00', 'base']])
        ->and($calculator->nights(['2026-12-15' => rateOf('2026-12-15', '9500.00'), '2026-12-16' => null], 2, new Occupancy(2)))->toBeNull();
});

it('prices a whole cottage by its cottage-type rate, else the sum of its rooms less the discount', function (): void {
    $calculator = new PriceCalculator;
    $cottage = ['2026-12-15' => rateOf('2026-12-15', '25000.00'), '2026-12-16' => null];
    $rooms = [
        ['2026-12-15' => rateOf('2026-12-15', '9500.00'), '2026-12-16' => rateOf('2026-12-16', '9500.00')],
        ['2026-12-15' => rateOf('2026-12-15', '6000.00'), '2026-12-16' => rateOf('2026-12-16', '6000.00')],
    ];

    $nights = $calculator->cottageNights($cottage, $rooms, '10');

    expect(array_map(fn (NightPrice $n): array => [$n->base, $n->source], $nights ?? []))->toBe([['25000.00', 'base'], ['13950.00', 'rooms']])
        ->and($calculator->cottageNights(['2026-12-16' => null], [['2026-12-16' => null]], '0'))->toBeNull()
        ->and($calculator->cottageNights(['2026-12-16' => null], [], '0'))->toBeNull();
});

it('spreads a discount over the nights in proportion, adding up exactly', function (): void {
    $calculator = new PriceCalculator;

    expect($calculator->spread('1000.00', ['6000.00', '6000.00', '7000.00']))->toBe(['315.79', '315.79', '368.42'])
        ->and(bcadd(bcadd('315.79', '315.79', 2), '368.42', 2))->toBe('1000.00')
        ->and($calculator->spread('0', ['6000.00']))->toBe(['0.00'])
        ->and($calculator->spread('99999', ['100.00', '50.00']))->toBe(['100.00', '50.00']);
});
