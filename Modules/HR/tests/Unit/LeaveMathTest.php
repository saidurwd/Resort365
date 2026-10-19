<?php

/*
| The arithmetic of leave (Step 6.3): charged days, entitlement with pro rata and monthly accrual, carry forward, and the
| available balance.
*/
use Carbon\CarbonImmutable;
use Modules\HR\Enums\LeaveAccrual;
use Modules\HR\Services\LeaveBalanceMath;
use Modules\HR\Services\LeaveDayCounter;

function lmDate(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date);
}

it('charges every day except holidays and days off, unless the type counts them, and splits by year', function (): void {
    $notWorked = ['2026-03-04' => true, '2026-03-06' => true];

    $normal = LeaveDayCounter::count('2026-03-02', '2026-03-08', false, false, $notWorked);
    $all = LeaveDayCounter::count('2026-03-02', '2026-03-08', false, true, $notWorked);
    $newYear = LeaveDayCounter::count('2026-12-30', '2027-01-02', false, false, []);

    expect($normal['days'])->toBe('5.0')->and($normal['dates'])->not->toContain('2026-03-04')->and($all['days'])->toBe('7.0')
        ->and($newYear['byYear'])->toBe(['2026' => '2.0', '2027' => '2.0'])->and($newYear['days'])->toBe('4.0');
});

it('charges a half day as 0.5, and nothing on a day off', function (): void {
    expect(LeaveDayCounter::count('2026-03-02', '2026-03-02', true, false, [])['days'])->toBe('0.5')->and(LeaveDayCounter::count('2026-03-04', '2026-03-04', true, false, ['2026-03-04' => true])['days'])->toBe('0.0')
        ->and(LeaveDayCounter::count('2026-03-04', '2026-03-04', true, false, ['2026-03-04' => true])['dates'])->toBe([]);
});

it('adds up the available balance from its parts', function (): void {
    expect(LeaveBalanceMath::available('14.0', '3.0', '-1.0', '5.5', '2.0'))->toBe('8.5')->and(LeaveBalanceMath::available('0', '0', '0', '1', '0'))->toBe('-1.0');
});

it('gives the whole year at once, or a twelfth a month, from the first month of eligibility', function (): void {
    $joined = lmDate('2024-01-10');

    expect(LeaveBalanceMath::entitlement('12', LeaveAccrual::Yearly, $joined, 0, lmDate('2026-03-15'), 2026))->toBe('12.0')
        ->and(LeaveBalanceMath::entitlement('12', LeaveAccrual::Monthly, $joined, 0, lmDate('2026-03-15'), 2026))->toBe('3.0')
        ->and(LeaveBalanceMath::entitlement('14', LeaveAccrual::Monthly, $joined, 0, lmDate('2026-12-31'), 2026))->toBe('14.0')
        ->and(LeaveBalanceMath::entitlement('14', LeaveAccrual::Monthly, $joined, 0, lmDate('2026-05-01'), 2025))->toBe('14.0')
        ->and(LeaveBalanceMath::entitlement('14', LeaveAccrual::Yearly, $joined, 0, lmDate('2026-05-01'), 2027))->toBeNull();
});

it('gives a new joiner the rest of the year pro rata, and waits for the months of service', function (): void {
    $joined = lmDate('2026-07-20');

    expect(LeaveBalanceMath::entitlement('12', LeaveAccrual::Yearly, $joined, 0, lmDate('2026-08-01'), 2026))->toBe('6.0')
        ->and(LeaveBalanceMath::entitlement('14', LeaveAccrual::Yearly, $joined, 12, lmDate('2026-08-01'), 2026))->toBeNull()
        ->and(LeaveBalanceMath::entitlement('14', LeaveAccrual::Yearly, $joined, 12, lmDate('2027-07-19'), 2027))->toBeNull()
        ->and(LeaveBalanceMath::entitlement('14', LeaveAccrual::Yearly, $joined, 12, lmDate('2027-07-20'), 2027))->toBe('7.0')
        ->and(LeaveBalanceMath::entitlement('14', LeaveAccrual::Yearly, $joined, 12, lmDate('2028-02-01'), 2028))->toBe('14.0')
        ->and(LeaveBalanceMath::entitlement('14', LeaveAccrual::Monthly, $joined, 12, lmDate('2027-09-30'), 2027))->toBe('3.5');
});

it('stops earning when someone leaves', function (): void {
    $joined = lmDate('2020-01-01');

    expect(LeaveBalanceMath::entitlement('12', LeaveAccrual::Yearly, $joined, 0, lmDate('2026-08-01'), 2026, lmDate('2026-04-30')))->toBe('4.0')
        ->and(LeaveBalanceMath::entitlement('12', LeaveAccrual::Yearly, $joined, 0, lmDate('2026-08-01'), 2026, lmDate('2025-12-31')))->toBe('0.0');
});

it('carries forward the unused days up to the limit, never a negative number', function (): void {
    expect(LeaveBalanceMath::carryForward('7.5', '5'))->toBe('5.0')->and(LeaveBalanceMath::carryForward('3.5', '5'))->toBe('3.5')->and(LeaveBalanceMath::carryForward('-2', '5'))->toBe('0.0')->and(LeaveBalanceMath::carryForward('4', '0'))->toBe('0.0');
});
