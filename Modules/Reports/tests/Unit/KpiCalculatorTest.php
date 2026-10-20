<?php

/*
| Occupancy, ADR and RevPAR (Step 7.1), worked out from sums, never from averaged percentages.
*/

use Modules\Reports\Services\Calculation\KpiCalculator;

require_once __DIR__.'/../Support/reports-fixtures.php';

it('works out occupancy, ADR and RevPAR from the sums', function (): void {
    $kpis = (new KpiCalculator)->calculate(rpTotals(['days' => 3, 'roomsAvailable' => 45, 'roomsOccupied' => 30, 'roomRevenue' => '180000.00']));

    expect($kpis->occupancy)->toBe('66.7')->and($kpis->adr)->toBe('6000.00')->and($kpis->revpar)->toBe('4000.00')->and($kpis->roomRevenue)->toBe('180000.00')->and($kpis->fnbSales)->toBe('12000.00')->and($kpis->guests)->toBe(20);
});

it('weighs each room night the same, however the days are split', function (): void {
    // A full 10-room day with 100,000 and a quiet 30-room day with 3 rooms and 12,000: 13 of 40 room nights sold, not (100% + 10%) ÷ 2.
    $kpis = (new KpiCalculator)->calculate(rpTotals(['days' => 2, 'roomsAvailable' => 40, 'roomsOccupied' => 13, 'roomRevenue' => '112000.00']));

    expect($kpis->occupancy)->toBe('32.5')->and($kpis->adr)->toBe('8615.38')->and($kpis->revpar)->toBe('2800.00');
});

it('answers zeros for a period with nothing to divide by', function (): void {
    $kpis = (new KpiCalculator)->calculate(rpTotals(['days' => 0, 'roomsAvailable' => 0, 'roomsOccupied' => 0, 'roomRevenue' => '0.00', 'fnbSales' => '0.00']));

    expect($kpis->occupancy)->toBe('0.0')->and($kpis->adr)->toBe('0.00')->and($kpis->revpar)->toBe('0.00');
});

it('gives an ADR of zero when rooms were available but none sold', function (): void {
    $kpis = (new KpiCalculator)->calculate(rpTotals(['roomsAvailable' => 15, 'roomsOccupied' => 0, 'roomRevenue' => '0.00']));

    expect($kpis->occupancy)->toBe('0.0')->and($kpis->adr)->toBe('0.00')->and($kpis->revpar)->toBe('0.00');
});
