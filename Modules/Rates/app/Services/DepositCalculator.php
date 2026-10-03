<?php

namespace Modules\Rates\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Rates\DTOs\DepositQuote;
use Modules\Rates\DTOs\DepositTerms;
use Modules\Rates\Enums\BalanceDueRule;
use Modules\Rates\Enums\DepositType;

/**
 * The advance deposit of a booking (ARCHITECTURE §6.5), without database access:
 *
 * - deposit = round(grand total × percent / 100, 2) for percentage policies; the percent is
 *   negotiable per booking (Q7) and defaults to the policy's default_percent;
 * - fixed amount and first night are capped at the grand total; "none" asks for nothing;
 * - when arrival is closer than full_payment_within_hours, the whole stay is due now;
 * - the deposit is due due_within_minutes after booking, but never after arrival;
 * - the balance is due at check-in, or N days before arrival (not before the booking date).
 */
class DepositCalculator
{
    public function quote(DepositTerms $terms, string $grandTotal, string $firstNight, ?string $percent, CarbonImmutable $bookedAt, CarbonImmutable $arrivalAt): DepositQuote
    {
        $total = BigDecimal::of($grandTotal)->toScale(2, RoundingMode::HalfUp);
        $fullPayment = $terms->fullPaymentWithinHours !== null && $bookedAt->diffInHours($arrivalAt, false) < $terms->fullPaymentWithinHours;

        $amount = match (true) {
            $fullPayment => $total,
            $terms->type === DepositType::None => BigDecimal::zero(),
            $terms->type === DepositType::Percentage => $this->percentOf($total, $percent ?? $terms->defaultPercent),
            $terms->type === DepositType::FixedAmount => BigDecimal::min(BigDecimal::of($terms->fixedAmount ?? '0'), $total),
            $terms->type === DepositType::FirstNight => BigDecimal::min(BigDecimal::of($firstNight), $total),
        };
        $amount = $amount->toScale(2, RoundingMode::HalfUp);

        $effectivePercent = $total->isZero() ? BigDecimal::zero() : $amount->multipliedBy(100)->dividedBy($total, 2, RoundingMode::HalfUp);
        $dueAt = $amount->isZero() ? null : CarbonImmutable::createFromTimestamp(min($bookedAt->addMinutes($terms->dueWithinMinutes)->getTimestamp(), $arrivalAt->getTimestamp()), $bookedAt->getTimezone());

        return new DepositQuote(
            percent: (string) $effectivePercent,
            amount: (string) $amount,
            balance: (string) $total->minus($amount),
            dueAt: $dueAt?->toIso8601String(),
            balanceDueOn: $this->balanceDueOn($terms, $bookedAt, $arrivalAt),
            fullPaymentRequired: $fullPayment,
            autoCancelUnpaid: $terms->autoCancelUnpaid,
        );
    }

    /**
     * Whether staff may ask for this percentage without the override permission.
     */
    public function isWithinLimits(DepositTerms $terms, string $percent): bool
    {
        $value = BigDecimal::of($percent);

        return ($terms->minPercent === null || $value->isGreaterThanOrEqualTo($terms->minPercent))
            && ($terms->maxPercent === null || $value->isLessThanOrEqualTo($terms->maxPercent));
    }

    private function percentOf(BigDecimal $total, string $percent): BigDecimal
    {
        $percent = BigDecimal::of($percent);

        if ($percent->isNegative() || $percent->isGreaterThan(100)) {
            throw new InvalidArgumentException("A deposit of {$percent}% is not possible.");
        }

        return $total->multipliedBy($percent)->dividedBy(100, 2, RoundingMode::HalfUp);
    }

    private function balanceDueOn(DepositTerms $terms, CarbonImmutable $bookedAt, CarbonImmutable $arrivalAt): string
    {
        if ($terms->balanceDueRule === BalanceDueRule::AtCheckIn || $terms->balanceDueDays === null) {
            return $arrivalAt->toDateString();
        }

        $due = $arrivalAt->startOfDay()->subDays($terms->balanceDueDays);

        return ($due->lessThan($bookedAt->startOfDay()) ? $bookedAt : $due)->toDateString();
    }
}
