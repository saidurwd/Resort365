<?php

namespace Modules\HR\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\HR\Enums\LeaveAccrual;

/**
 * The arithmetic of leave balances (Step 6.3). Pure; days are decimal strings with one decimal.
 */
final class LeaveBalanceMath
{
    /** Available = entitled + carried forward + adjusted - used - pending. */
    public static function available(string $entitled, string $carriedForward, string $adjusted, string $used, string $pending): string
    {
        return (string) BigDecimal::of($entitled)->plus($carriedForward)->plus($adjusted)->minus($used)->minus($pending)->toScale(1, RoundingMode::HalfUp);
    }

    /**
     * The days an employee has earned in a year as of a date, or null when nothing is due yet.
     *
     * - Entitlement starts after the months of service the policy asks for (that month counts); an employee who joined or
     *   becomes eligible mid-year gets the rest of the year pro rata.
     * - "Yearly" gives the whole (pro-rata) amount at once, "monthly" a twelfth of the yearly days for each month up to the
     *   date. Someone who leaves earns only up to their last month.
     */
    public static function entitlement(string $annualDays, LeaveAccrual $accrual, CarbonImmutable $joinedOn, int $minServiceMonths, CarbonImmutable $asOf, int $year, ?CarbonImmutable $exitedOn = null): ?string
    {
        $eligibleFrom = $joinedOn->addMonthsNoOverflow($minServiceMonths);

        if ($eligibleFrom->gt($asOf) || $eligibleFrom->year > $year || $asOf->year < $year) {
            return null;
        }

        $startMonth = $eligibleFrom->year < $year ? 1 : $eligibleFrom->month;
        $lastMonth = $accrual === LeaveAccrual::Monthly && $asOf->year === $year ? $asOf->month : 12;

        if ($exitedOn instanceof CarbonImmutable && $exitedOn->year <= $year) {
            $lastMonth = $exitedOn->year < $year ? 0 : min($lastMonth, $exitedOn->month);
        }

        $months = max(0, $lastMonth - $startMonth + 1);

        return (string) BigDecimal::of($annualDays)->multipliedBy($months)->dividedBy(12, 1, RoundingMode::HalfUp);
    }

    /** The unused days that carry into the next year, up to the policy's limit. */
    public static function carryForward(string $unused, string $max): string
    {
        $unused = BigDecimal::of($unused);
        $limit = BigDecimal::of($max);

        return (string) ($unused->isNegative() ? BigDecimal::zero() : ($unused->isGreaterThan($limit) ? $limit : $unused))->toScale(1, RoundingMode::HalfUp);
    }
}
