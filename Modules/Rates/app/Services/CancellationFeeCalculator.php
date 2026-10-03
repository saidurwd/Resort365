<?php

namespace Modules\Rates\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Modules\Rates\DTOs\CancellationQuote;
use Modules\Rates\DTOs\CancellationRuleData;
use Modules\Rates\DTOs\CancellationTerms;
use Modules\Rates\Enums\CancellationChargeType;

/**
 * What cancelling a booking costs (ARCHITECTURE §5.5), without database access. The tier that
 * covers the days before arrival applies; a no-show uses the policy's no-show charge, or else the
 * arrival-day tier. The fee never exceeds the stay total (nor the deposit for "% of deposit");
 * the refund is what was paid above the fee.
 */
class CancellationFeeCalculator
{
    /**
     * @param  list<string>  $nightlyTotals  the stay's nights in order (for "number of nights")
     * @param  int|null  $daysBeforeArrival  null for a no-show
     */
    public function quote(CancellationTerms $terms, string $stayTotal, array $nightlyTotals, string $deposit, string $paid, ?int $daysBeforeArrival): CancellationQuote
    {
        $noShow = $daysBeforeArrival === null;
        $rule = $this->rule($terms, max(0, $daysBeforeArrival ?? 0));

        [$type, $value] = $noShow && $terms->noShowChargeType instanceof CancellationChargeType
            ? [$terms->noShowChargeType, $terms->noShowChargeValue ?? '0']
            : [$rule?->chargeType, $rule->chargeValue ?? '0'];

        $total = BigDecimal::of($stayTotal);
        $fee = $type instanceof CancellationChargeType ? $this->charge($type, $value, $total, BigDecimal::of($deposit), $nightlyTotals) : BigDecimal::zero();
        $fee = BigDecimal::min($fee, $total)->toScale(2, RoundingMode::HalfUp);
        $paid = BigDecimal::of($paid);

        return new CancellationQuote(
            fee: (string) $fee,
            refund: (string) BigDecimal::max($paid->minus($fee), BigDecimal::zero())->toScale(2),
            owed: (string) BigDecimal::max($fee->minus($paid), BigDecimal::zero())->toScale(2),
            rule: $noShow && $terms->noShowChargeType instanceof CancellationChargeType ? null : $rule,
            noShow: $noShow,
        );
    }

    /**
     * Whole days from the cancellation date to the arrival date (negative after arrival).
     */
    public static function daysBefore(CarbonInterface $arrivalDate, CarbonInterface $cancelledOn): int
    {
        return (int) $cancelledOn->copy()->startOfDay()->diffInDays($arrivalDate->copy()->startOfDay(), false);
    }

    private function rule(CancellationTerms $terms, int $daysBefore): ?CancellationRuleData
    {
        foreach ($terms->rules as $rule) {
            if ($rule->covers($daysBefore)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $nightlyTotals
     */
    private function charge(CancellationChargeType $type, string $value, BigDecimal $total, BigDecimal $deposit, array $nightlyTotals): BigDecimal
    {
        return match ($type) {
            CancellationChargeType::PercentOfTotal => $total->multipliedBy($value)->dividedBy(100, 2, RoundingMode::HalfUp),
            CancellationChargeType::PercentOfDeposit => BigDecimal::min($deposit->multipliedBy($value)->dividedBy(100, 2, RoundingMode::HalfUp), $deposit),
            CancellationChargeType::Nights => array_reduce(array_slice($nightlyTotals, 0, max(0, (int) $value)),
                fn (BigDecimal $sum, string $night): BigDecimal => $sum->plus($night), BigDecimal::zero()),
            CancellationChargeType::Fixed => BigDecimal::of($value),
        };
    }
}
