<?php

/*
| F&B trends (Step 7.2): sales and covers by day, week or month, the change against the one before, and against the period before.
| Pure.
*/

use Modules\Restaurant\Services\Reports\TrendBuilder;

/**
 * A fortnight of sales: 1,000 a day with 10 covers, the second week doubled; the week before, 500 a day.
 *
 * @return array<string, array{sales: string, covers: int, bills: int}>
 */
function trDays(): array
{
    $days = [];

    for ($d = 0; $d < 14; $d++) {
        $date = date('Y-m-d', strtotime("2026-03-02 +{$d} days"));
        $days[$date] = ['sales' => $d < 7 ? '1000.00' : '2000.00', 'covers' => 10, 'bills' => 4];
    }

    for ($d = 1; $d <= 14; $d++) {
        $days[date('Y-m-d', strtotime("2026-03-02 -{$d} days"))] = ['sales' => '500.00', 'covers' => 5, 'bills' => 2];
    }

    return $days;
}

it('adds the days up by week, Monday to Sunday, with the change against the week before', function (): void {
    $result = (new TrendBuilder)->build(trDays(), '2026-03-02', '2026-03-15', 'week');

    expect($result['rows'])->toHaveCount(2)->and($result['rows'][0])->toBe(['key' => '2026-03-02', 'label' => '02 Mar – 08 Mar', 'sales' => '7000.00', 'covers' => 70, 'bills' => 28, 'per_cover' => '100.00', 'change' => null])
        ->and($result['rows'][1]['sales'])->toBe('14000.00')->and($result['rows'][1]['change'])->toBe('100.0')->and($result['rows'][1]['per_cover'])->toBe('200.00')
        ->and($result['best'])->toBe(['label' => '09 Mar – 15 Mar', 'sales' => '14000.00']);
});

it('compares the whole range with the equal range before it', function (): void {
    $totals = (new TrendBuilder)->build(trDays(), '2026-03-02', '2026-03-15', 'week')['totals'];

    expect($totals)->toBe(['sales' => '21000.00', 'covers' => 140, 'bills' => 56, 'per_cover' => '150.00', 'previous_sales' => '7000.00', 'change' => '200.0']);
});

it('shows each day, with the empty ones as zero', function (): void {
    $days = ['2026-03-02' => ['sales' => '300.00', 'covers' => 3, 'bills' => 1]];
    $result = (new TrendBuilder)->build($days, '2026-03-02', '2026-03-04', 'day');

    expect($result['rows'])->toHaveCount(3)->and($result['rows'][0]['label'])->toBe('Mon 02 Mar')->and($result['rows'][1])->toBe(['key' => '2026-03-03', 'label' => 'Tue 03 Mar', 'sales' => '0.00', 'covers' => 0, 'bills' => 0, 'per_cover' => '0.00', 'change' => '-100.0'])
        ->and($result['rows'][2]['change'])->toBeNull()->and($result['totals']['change'])->toBeNull();
});

it('adds the days up by month', function (): void {
    $days = ['2026-02-27' => ['sales' => '100.00', 'covers' => 2, 'bills' => 1], '2026-03-01' => ['sales' => '200.00', 'covers' => 4, 'bills' => 1], '2026-03-31' => ['sales' => '300.00', 'covers' => 6, 'bills' => 2]];
    $result = (new TrendBuilder)->build($days, '2026-02-27', '2026-03-31', 'month');

    expect(array_column($result['rows'], 'label'))->toBe(['February 2026', 'March 2026'])->and($result['rows'][0]['sales'])->toBe('100.00')->and($result['rows'][1]['sales'])->toBe('500.00')->and($result['rows'][1]['change'])->toBe('400.0');
});

it('takes anything but day, week or month as days', function (): void {
    $result = (new TrendBuilder)->build([], '2026-03-02', '2026-03-03', 'fortnight');

    expect($result['rows'])->toHaveCount(2)->and($result['best'])->toBeNull();
});
