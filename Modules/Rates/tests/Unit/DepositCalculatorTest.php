<?php

/*
| Step 1.4 "Done when": the worked example in ARCHITECTURE §6.5 is reproduced exactly.
*/
use Carbon\CarbonImmutable;
use Modules\Core\DTOs\TaxLine;
use Modules\Core\DTOs\TaxRule;
use Modules\Core\Enums\TaxType;
use Modules\Core\Services\TaxCalculator;
use Modules\Rates\DTOs\DepositTerms;
use Modules\Rates\Enums\BalanceDueRule;
use Modules\Rates\Enums\DepositType;
use Modules\Rates\Services\DepositCalculator;

/**
 * @param  array<string, mixed>  $overrides
 */
function depositTerms(array $overrides = []): DepositTerms
{
    return DepositTerms::from([
        'type' => DepositType::Percentage, 'minPercent' => '30', 'defaultPercent' => '30', 'maxPercent' => '50', 'fixedAmount' => null,
        'dueWithinMinutes' => 30, 'autoCancelUnpaid' => true, 'balanceDueRule' => BalanceDueRule::AtCheckIn, ...$overrides,
    ]);
}

function booked(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-01 10:00', 'Asia/Dhaka');
}

function arriving(string $date = '2026-12-20 14:00'): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'Asia/Dhaka');
}

it('reproduces the worked example in ARCHITECTURE §6.5 exactly', function (): void {
    // Sunset Villa (whole) 3 × 12,000 + Room B-1 3 × 5,000.
    $subtotal = bcadd(bcmul('3', '12000.00', 2), bcmul('3', '5000.00', 2), 2);
    $taxes = new TaxCalculator()->calculate($subtotal, [
        new TaxRule('SC', 'Service charge', TaxType::Percent, '10'),
        new TaxRule('VAT', 'VAT', TaxType::Percent, '15', compound: true),
    ]);
    $deposit = new DepositCalculator()->quote(depositTerms(), $taxes->gross, '17000.00', '30', booked(), arriving());

    expect($subtotal)->toBe('51000.00')
        ->and(array_map(fn (TaxLine $line) => $line->amount, $taxes->taxes))->toBe(['5100.00', '8415.00'])
        ->and($taxes->gross)->toBe('64515.00')
        ->and($deposit->amount)->toBe('19354.50')
        ->and($deposit->balance)->toBe('45160.50')
        ->and($deposit->percent)->toBe('30.00')
        ->and($deposit->balanceDueOn)->toBe('2026-12-20');
});

it('uses the default percent, or any negotiated percent', function (): void {
    $calculator = new DepositCalculator;

    expect($calculator->quote(depositTerms(['defaultPercent' => '40']), '10000', '5000', null, booked(), arriving())->amount)->toBe('4000.00')
        ->and($calculator->quote(depositTerms(), '10000', '5000', '12.5', booked(), arriving())->amount)->toBe('1250.00')
        ->and($calculator->quote(depositTerms(), '999.99', '500', '33.33', booked(), arriving())->amount)->toBe('333.30');
});

it('tells whether a percent is within the policy limits (empty limits allow anything)', function (): void {
    $calculator = new DepositCalculator;

    expect($calculator->isWithinLimits(depositTerms(), '30'))->toBeTrue()
        ->and($calculator->isWithinLimits(depositTerms(), '50'))->toBeTrue()
        ->and($calculator->isWithinLimits(depositTerms(), '29.99'))->toBeFalse()
        ->and($calculator->isWithinLimits(depositTerms(), '51'))->toBeFalse()
        ->and($calculator->isWithinLimits(depositTerms(['minPercent' => null, 'maxPercent' => null]), '5'))->toBeTrue();
});

it('refuses impossible percentages', function (): void {
    new DepositCalculator()->quote(depositTerms(), '1000', '500', '120', booked(), arriving());
})->throws(InvalidArgumentException::class);

it('handles fixed amount, first night and no-deposit policies', function (): void {
    $calculator = new DepositCalculator;
    $fixed = $calculator->quote(depositTerms(['type' => DepositType::FixedAmount, 'fixedAmount' => '5000']), '20000', '8000', null, booked(), arriving());
    $capped = $calculator->quote(depositTerms(['type' => DepositType::FixedAmount, 'fixedAmount' => '5000']), '3000', '3000', null, booked(), arriving());
    $firstNight = $calculator->quote(depositTerms(['type' => DepositType::FirstNight]), '20000', '8000', null, booked(), arriving());
    $none = $calculator->quote(depositTerms(['type' => DepositType::None]), '20000', '8000', null, booked(), arriving());

    expect([$fixed->amount, $fixed->percent, $fixed->balance])->toBe(['5000.00', '25.00', '15000.00'])
        ->and($capped->amount)->toBe('3000.00')
        ->and($firstNight->amount)->toBe('8000.00')
        ->and([$none->amount, $none->balance, $none->dueAt])->toBe(['0.00', '20000.00', null]);
});

it('makes the deposit due 30 minutes after booking, but never after arrival', function (): void {
    $calculator = new DepositCalculator;

    expect($calculator->quote(depositTerms(), '10000', '5000', null, booked(), arriving())->dueAt)->toBe('2026-11-01T10:30:00+06:00')
        ->and($calculator->quote(depositTerms(['dueWithinMinutes' => 2880]), '10000', '5000', null, booked(), arriving('2026-11-02 14:00'))->dueAt)
        ->toBe('2026-11-02T14:00:00+06:00')
        ->and($calculator->quote(depositTerms(), '10000', '5000', null, booked(), arriving())->autoCancelUnpaid)->toBeTrue();
});

it('asks for full payment when arrival is closer than the full-payment window', function (): void {
    $terms = depositTerms(['fullPaymentWithinHours' => 24]);
    $tomorrow = new DepositCalculator()->quote($terms, '10000', '5000', '30', booked(), arriving('2026-11-02 08:00'));
    $later = new DepositCalculator()->quote($terms, '10000', '5000', '30', booked(), arriving('2026-11-03 14:00'));

    expect([$tomorrow->fullPaymentRequired, $tomorrow->amount, $tomorrow->balance])->toBe([true, '10000.00', '0.00'])
        ->and([$later->fullPaymentRequired, $later->amount])->toBe([false, '3000.00']);
});

it('makes the balance due at check-in or a number of days before arrival', function (): void {
    $calculator = new DepositCalculator;
    $sevenDays = depositTerms(['balanceDueRule' => BalanceDueRule::DaysBeforeArrival, 'balanceDueDays' => 7]);

    expect($calculator->quote($sevenDays, '10000', '5000', null, booked(), arriving())->balanceDueOn)->toBe('2026-12-13')
        ->and($calculator->quote($sevenDays, '10000', '5000', null, booked(), arriving('2026-11-04 14:00'))->balanceDueOn)->toBe('2026-11-01');
});
