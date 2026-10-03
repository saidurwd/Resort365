<?php

namespace Modules\Reservation\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Reservation\Enums\PaymentStatus;

/**
 * A reservation's money rules (ARCHITECTURE §6.4, §6.5), without database access. Amounts are
 * decimal strings with two places.
 */
class ReservationBalance
{
    /**
     * Rule 1: deposit = round(grand total × percent / 100), half up to the cent.
     */
    public function deposit(string $grandTotal, string $percent): string
    {
        return (string) BigDecimal::of($grandTotal)->multipliedBy($percent)->dividedBy(100, 2, RoundingMode::HalfUp);
    }

    /**
     * What is still owed: the total (or the cancellation fee of a cancelled booking) less what was paid, never below zero.
     */
    public function balance(string $owedTotal, string $paid): string
    {
        return (string) BigDecimal::max(BigDecimal::of($owedTotal)->minus($paid), BigDecimal::zero())->toScale(2);
    }

    /**
     * Unpaid until the deposit is met; then deposit paid; fully paid at the total; overpaid above it.
     * A booking with no deposit is "deposit paid" as soon as anything is paid.
     */
    public function status(string $grandTotal, string $paid, string $depositRequired): PaymentStatus
    {
        $paid = BigDecimal::of($paid);
        $total = BigDecimal::of($grandTotal);

        return match (true) {
            $paid->isZero() || $paid->isNegative() => PaymentStatus::Unpaid,
            $paid->isGreaterThan($total) => PaymentStatus::Overpaid,
            $paid->isEqualTo($total) => PaymentStatus::FullyPaid,
            $paid->isGreaterThanOrEqualTo($depositRequired) => PaymentStatus::DepositPaid,
            default => PaymentStatus::Unpaid,
        };
    }

    /**
     * Whether what was paid covers the deposit (a zero deposit is always met).
     */
    public function depositMet(string $paid, string $depositRequired): bool
    {
        return BigDecimal::of($paid)->isGreaterThanOrEqualTo($depositRequired);
    }
}
