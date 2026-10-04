<?php

/*
| DailyStatsCalculator: occupancy, ADR and RevPAR for one night (USALI definitions).
*/

use Modules\FrontOffice\Services\DailyStatsCalculator;

it('takes out-of-order rooms out of the rooms available', function (): void {
    // 15 rooms, 1 out of order → 14 available; 9 sold for 55,000.
    expect((new DailyStatsCalculator)->calculate(15, 1, 9, '55000.00'))->toBe([
        'rooms_available' => 14,
        'occupancy_percent' => '64.29',
        'adr' => '6111.11',
        'revpar' => '3928.57',
    ]);
});

it('gives zeros when nothing was sold or nothing could be', function (): void {
    $calculator = new DailyStatsCalculator;

    expect($calculator->calculate(15, 0, 0, '0.00'))->toBe(['rooms_available' => 15, 'occupancy_percent' => '0.00', 'adr' => '0.00', 'revpar' => '0.00'])
        ->and($calculator->calculate(2, 2, 0, '0.00'))->toBe(['rooms_available' => 0, 'occupancy_percent' => '0.00', 'adr' => '0.00', 'revpar' => '0.00']);
});

it('counts a full house as 100%', function (): void {
    expect((new DailyStatsCalculator)->calculate(10, 0, 10, '60000.00'))->toMatchArray(['occupancy_percent' => '100.00', 'adr' => '6000.00', 'revpar' => '6000.00']);
});
