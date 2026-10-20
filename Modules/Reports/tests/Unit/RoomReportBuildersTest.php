<?php

/*
| The rooms reports (Step 7.1): forecast, booking pace, source mix, cancellations and length of stay, from booking facts. Pure.
*/
use Modules\Reports\Services\Calculation\CancellationBuilder;
use Modules\Reports\Services\Calculation\ForecastBuilder;
use Modules\Reports\Services\Calculation\LengthOfStayBuilder;
use Modules\Reports\Services\Calculation\PaceBuilder;
use Modules\Reports\Services\Calculation\SourceMixBuilder;
use Modules\Reservation\DTOs\ForecastDay;
use Tests\TestCase;

require_once __DIR__.'/../Support/reports-fixtures.php';

// Labels go through the translator, so the application is booted (no database).
uses(TestCase::class);

it('forecasts the occupancy on the books for each coming night', function (): void {
    $table = (new ForecastBuilder)->build([
        new ForecastDay('2026-03-10', 10, 1, 4, 2), new ForecastDay('2026-03-11', 10, 1, 9, 0), new ForecastDay('2026-03-12', 10, 1, 12, 5),
    ], 'Forecast');

    // 9 rooms to sell each night; 12 booked on the last night cannot show above 100%, but the total counts what is on the books.
    expect($table->rows[0])->toBe(['2026-03-10', 'Tue', '9', '4', '44.4%', '2'])->and($table->rows[1][4])->toBe('100.0%')->and($table->rows[2][4])->toBe('100.0%')
        ->and($table->totals)->toBe(['Total', '', '27', '25', '92.6%', '7'])->and($table->summary[2]['value'])->toBe('2026-03-11 (100.0%)');
});

it('groups bookings by how far ahead they were made, leaving cancellations out', function (): void {
    $bookings = [
        rpFact(['bookedOn' => '2026-03-10']),                                   // 0 days
        rpFact(['bookedOn' => '2026-03-05', 'total' => '20000.00']),            // 5 days
        rpFact(['bookedOn' => '2026-02-18', 'nights' => 3, 'rooms' => 2]),      // 20 days, 6 room nights
        rpFact(['bookedOn' => '2026-01-24']),                                   // 45 days
        rpFact(['bookedOn' => '2025-12-01']),                                   // 99 days
        rpFact(['bookedOn' => '2025-12-01', 'status' => 'cancelled']),          // left out
    ];
    $table = (new PaceBuilder)->build($bookings, 'Pace');

    expect($table->rows[0])->toBe(['0–7 days', '2', '4', '30,000.00', '40.0%', '2.5'])->and($table->rows[1][1])->toBe('1')->and($table->rows[1][2])->toBe('6')->and($table->rows[2][1])->toBe('1')->and($table->rows[3][1])->toBe('1')
        ->and($table->totals[0])->toBe('Total')->and($table->totals[1])->toBe('5')->and($table->summary[1]['value'])->toBe('33.8');
});

it('shows where bookings come from and each source\'s share of the revenue', function (): void {
    $table = (new SourceMixBuilder)->build([
        rpFact(['source' => 'online', 'total' => '30000.00']), rpFact(['source' => 'online', 'total' => '10000.00']), rpFact(['source' => 'online', 'status' => 'cancelled']),
        rpFact(['source' => 'phone', 'total' => '60000.00', 'nights' => 3, 'rooms' => 2]),
    ], 'Sources');

    // Phone brings 60,000 of 100,000; online 40,000 from two stays; the cancelled online booking brings nothing.
    expect($table->rows[0])->toBe(['Phone', '1', '0', '6', '60,000.00', '60,000.00', '60.0%'])->and($table->rows[1])->toBe(['Online', '3', '1', '4', '40,000.00', '20,000.00', '40.0%'])
        ->and($table->totals)->toBe(['Total', '4', '1', '10', '100,000.00', '33,333.33', '100.0%']);
});

it('counts cancellations, no-shows, fees and the notice given', function (): void {
    $table = (new CancellationBuilder)->build([
        rpFact(['source' => 'online']),
        rpFact(['source' => 'online', 'status' => 'cancelled', 'cancellationFee' => '500.00', 'cancelledOn' => '2026-03-01']),        // 9 days before
        rpFact(['source' => 'online', 'status' => 'cancelled', 'cancellationFee' => '0.00', 'cancelledOn' => '2026-03-08']),          // 2 days before
        rpFact(['source' => 'phone', 'status' => 'no_show']),
    ], 'Cancellations');

    expect($table->rows[0])->toBe(['Online', '3', '2', '66.7%', '0', '500.00', '5.5'])->and($table->rows[1])->toBe(['Phone', '1', '0', '0.0%', '1', '0.00', '—'])
        ->and($table->totals)->toBe(['Total', '4', '2', '50.0%', '1', '500.00', '5.5']);
});

it('counts stays by length, leaving cancellations and no-shows out, with the average stay', function (): void {
    $table = (new LengthOfStayBuilder)->build([
        rpFact(['nights' => 1]), rpFact(['nights' => 2]), rpFact(['nights' => 2, 'rooms' => 2]), rpFact(['nights' => 3]), rpFact(['nights' => 8]), rpFact(['nights' => 20]),
        rpFact(['nights' => 5, 'status' => 'cancelled']), rpFact(['nights' => 4, 'status' => 'no_show']),
    ], 'Length of stay');

    expect($table->rows[0])->toBe(['1 night', '1', '1', '16.7%'])->and($table->rows[1])->toBe(['2 nights', '2', '6', '33.3%'])->and($table->rows[6])->toBe(['8–14 nights', '1', '8', '16.7%'])->and($table->rows[7][1])->toBe('1')
        ->and($table->totals)->toBe(['Total', '6', '38', '100.0%'])->and($table->summary[0]['value'])->toBe('6.00');
});

it('answers an empty table for no bookings', function (): void {
    $pace = (new PaceBuilder)->build([], 'Pace');
    $stay = (new LengthOfStayBuilder)->build([], 'Stay');

    expect($pace->totals[1])->toBe('0')->and($pace->rows[0][4])->toBe('—')->and($stay->totals[1])->toBe('0')->and($stay->summary[0]['value'])->toBe('—');
});
