<?php

use Carbon\CarbonImmutable;
use Modules\Billing\Services\AgingCalculator;

it('buckets open amounts by days past due', function (): void {
    $today = CarbonImmutable::parse('2026-12-31');
    $due = fn (int $daysAgo): array => ['due_on' => $today->subDays($daysAgo)];

    expect(new AgingCalculator()->buckets([
        ['open' => '1000.00', ...$due(-5)],   // not due yet
        ['open' => '200.00', ...$due(0)],     // due today: current
        ['open' => '300.00', ...$due(1)],
        ['open' => '400.00', ...$due(30)],
        ['open' => '500.00', ...$due(31)],
        ['open' => '600.00', ...$due(90)],
        ['open' => '700.00', ...$due(91)],
    ], $today))->toBe([
        'current' => '1200.00', '1_30' => '700.00', '31_60' => '500.00', '61_90' => '600.00', 'over_90' => '700.00', 'total' => '3700.00',
    ]);
});

it('returns every bucket at zero when nothing is open', function (): void {
    expect(new AgingCalculator()->buckets([], CarbonImmutable::today()))
        ->toBe(['current' => '0.00', '1_30' => '0.00', '31_60' => '0.00', '61_90' => '0.00', 'over_90' => '0.00', 'total' => '0.00']);
});
