<?php

/*
| Step 1.4 "Done when": cancellation fees are correct for each tier.
| The Flexible policy is the example in ARCHITECTURE §5.5: more than 14 days: full refund;
| 7–14 days: 50% of the deposit retained; under 7 days or no-show: deposit forfeited.
*/
use Carbon\CarbonImmutable;
use Modules\Rates\DTOs\CancellationQuote;
use Modules\Rates\DTOs\CancellationRuleData;
use Modules\Rates\DTOs\CancellationTerms;
use Modules\Rates\Enums\CancellationChargeType;
use Modules\Rates\Services\CancellationFeeCalculator;

function flexible(): CancellationTerms
{
    return new CancellationTerms([
        new CancellationRuleData(15, null, CancellationChargeType::PercentOfDeposit, '0'),
        new CancellationRuleData(7, 14, CancellationChargeType::PercentOfDeposit, '50'),
        new CancellationRuleData(0, 6, CancellationChargeType::PercentOfDeposit, '100'),
    ], CancellationChargeType::PercentOfDeposit, '100');
}

/**
 * The §6.5 booking: total 64,515.00, deposit 19,354.50 paid.
 */
function cancelFlexible(?int $daysBefore): CancellationQuote
{
    return new CancellationFeeCalculator()->quote(flexible(), '64515.00', ['21505.00', '21505.00', '21505.00'], '19354.50', '19354.50', $daysBefore);
}

it('charges each tier of the flexible policy, at its boundaries', function (?int $daysBefore, string $fee, string $refund): void {
    $quote = cancelFlexible($daysBefore);

    expect([$quote->fee, $quote->refund, $quote->owed])->toBe([$fee, $refund, '0.00']);
})->with([
    '30 days: full refund' => [30, '0.00', '19354.50'],
    '15 days: full refund' => [15, '0.00', '19354.50'],
    '14 days: half the deposit' => [14, '9677.25', '9677.25'],
    '7 days: half the deposit' => [7, '9677.25', '9677.25'],
    '6 days: deposit forfeited' => [6, '19354.50', '0.00'],
    'arrival day: deposit forfeited' => [0, '19354.50', '0.00'],
    'no-show: deposit forfeited' => [null, '19354.50', '0.00'],
]);

it('reports which tier applied, and no-shows separately', function (): void {
    expect(cancelFlexible(10)->rule?->daysBeforeTo)->toBe(14)
        ->and(cancelFlexible(null)->noShow)->toBeTrue()
        ->and(cancelFlexible(null)->rule)->toBeNull();
});

it('charges a percent of the total, a number of nights or a fixed amount', function (): void {
    $calculator = new CancellationFeeCalculator;
    $nightly = ['9500.00', '11000.00', '9500.00'];
    $policy = fn (CancellationChargeType $type, string $value): CancellationTerms => new CancellationTerms([new CancellationRuleData(0, null, $type, $value)]);

    expect($calculator->quote($policy(CancellationChargeType::PercentOfTotal, '25'), '30000.00', $nightly, '9000.00', '9000.00', 3)->fee)->toBe('7500.00')
        ->and($calculator->quote($policy(CancellationChargeType::Nights, '1'), '30000.00', $nightly, '9000.00', '9000.00', 3)->fee)->toBe('9500.00')
        ->and($calculator->quote($policy(CancellationChargeType::Nights, '2'), '30000.00', $nightly, '9000.00', '9000.00', 3)->fee)->toBe('20500.00')
        ->and($calculator->quote($policy(CancellationChargeType::Fixed, '2500'), '30000.00', $nightly, '9000.00', '9000.00', 3)->fee)->toBe('2500.00');
});

it('never charges more than the stay, and asks for what is owed above the amount paid', function (): void {
    $calculator = new CancellationFeeCalculator;
    $nonRefundable = new CancellationTerms([new CancellationRuleData(0, null, CancellationChargeType::PercentOfTotal, '100')]);
    $quote = $calculator->quote($nonRefundable, '30000.00', ['10000.00', '10000.00', '10000.00'], '9000.00', '9000.00', 20);
    $tooMuch = $calculator->quote(new CancellationTerms([new CancellationRuleData(0, null, CancellationChargeType::Nights, '5')]), '3000.00', ['1000.00', '1000.00', '1000.00'], '0', '0', 1);

    expect([$quote->fee, $quote->refund, $quote->owed])->toBe(['30000.00', '0.00', '21000.00'])
        ->and($tooMuch->fee)->toBe('3000.00');
});

it('uses the arrival-day tier for a no-show without its own charge, and charges nothing without a tier', function (): void {
    $calculator = new CancellationFeeCalculator;
    $tiersOnly = new CancellationTerms([new CancellationRuleData(0, 2, CancellationChargeType::PercentOfTotal, '50')]);

    expect($calculator->quote($tiersOnly, '1000.00', [], '300.00', '300.00', null)->fee)->toBe('500.00')
        ->and($calculator->quote($tiersOnly, '1000.00', [], '300.00', '300.00', 10)->fee)->toBe('0.00')
        ->and($calculator->quote(new CancellationTerms([]), '1000.00', [], '300.00', '300.00', 1)->refund)->toBe('300.00');
});

it('counts whole days before arrival from calendar dates', function (): void {
    $arrival = CarbonImmutable::parse('2026-12-20 14:00');

    expect(CancellationFeeCalculator::daysBefore($arrival, CarbonImmutable::parse('2026-12-06 23:59')))->toBe(14)
        ->and(CancellationFeeCalculator::daysBefore($arrival, CarbonImmutable::parse('2026-12-20 09:00')))->toBe(0)
        ->and(CancellationFeeCalculator::daysBefore($arrival, CarbonImmutable::parse('2026-12-21 09:00')))->toBe(-1);
});
