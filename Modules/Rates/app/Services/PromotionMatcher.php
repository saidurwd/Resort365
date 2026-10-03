<?php

namespace Modules\Rates\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Rates\DTOs\PromotionTerms;
use Modules\Rates\DTOs\StayRequest;
use Modules\Rates\Enums\DiscountType;

/**
 * Which promotions apply to a stay and what they are worth (ARCHITECTURE §5.5), without database
 * access. A promotion with a code applies only when that code is given (case-insensitive); one
 * without a code applies automatically. Percent and per-night discounts cover the nights inside
 * the stay window; a per-stay discount needs the arrival inside it. Promotions do not combine:
 * the best one wins.
 */
class PromotionMatcher
{
    /**
     * @param  list<PromotionTerms>  $promotions
     */
    public function best(array $promotions, StayRequest $stay): ?PromotionDiscount
    {
        $best = null;

        foreach ($promotions as $promotion) {
            $discount = $this->discount($promotion, $stay);

            if ($discount instanceof PromotionDiscount && (! $best instanceof PromotionDiscount || BigDecimal::of($discount->amount)->isGreaterThan($best->amount))) {
                $best = $discount;
            }
        }

        return $best;
    }

    /**
     * The discount of one promotion, or null when it does not apply.
     */
    public function discount(PromotionTerms $promotion, StayRequest $stay): ?PromotionDiscount
    {
        if (! $this->applies($promotion, $stay)) {
            return null;
        }

        $eligible = array_filter($stay->nightly, fn (string $amount, string $date): bool => $this->inWindow($date, $promotion->stayFrom, $promotion->stayTo), ARRAY_FILTER_USE_BOTH);
        $value = BigDecimal::of($promotion->discountValue);
        $stayTotal = array_reduce($stay->nightly, fn (BigDecimal $sum, string $night): BigDecimal => $sum->plus($night), BigDecimal::zero());

        $amount = match ($promotion->discountType) {
            DiscountType::Percent => array_reduce($eligible, fn (BigDecimal $sum, string $night): BigDecimal => $sum->plus($night), BigDecimal::zero())
                ->multipliedBy($value)->dividedBy(100, 2, RoundingMode::HalfUp),
            DiscountType::FixedPerNight => array_reduce($eligible, fn (BigDecimal $sum, string $night): BigDecimal => $sum->plus(BigDecimal::min($value, BigDecimal::of($night))), BigDecimal::zero()),
            DiscountType::FixedPerStay => BigDecimal::min($value, $stayTotal),
        };

        if ($amount->isZero()) {
            return null;
        }

        $nights = $promotion->discountType === DiscountType::FixedPerStay ? count($stay->nightly) : count($eligible);

        return new PromotionDiscount($promotion->id, $promotion->code, $promotion->name, (string) $amount->toScale(2), $nights);
    }

    private function applies(PromotionTerms $promotion, StayRequest $stay): bool
    {
        $dates = array_keys($stay->nightly);

        if (! $promotion->isActive || $dates === []) {
            return false;
        }

        $arrival = $dates[0];
        $nights = count($dates);
        $advance = (int) CarbonImmutable::parse($stay->bookedOn)->startOfDay()->diffInDays(CarbonImmutable::parse($arrival), false);

        return match (true) {
            $promotion->code !== null && strcasecmp($promotion->code, (string) $stay->promoCode) !== 0 => false,
            $promotion->usageLimit !== null && $promotion->timesUsed >= $promotion->usageLimit => false,
            ! $this->inWindow($stay->bookedOn, $promotion->bookFrom, $promotion->bookTo) => false,
            $promotion->discountType === DiscountType::FixedPerStay && ! $this->inWindow($arrival, $promotion->stayFrom, $promotion->stayTo) => false,
            $promotion->minNights !== null && $nights < $promotion->minNights => false,
            $promotion->maxNights !== null && $nights > $promotion->maxNights => false,
            $promotion->minAdvanceDays !== null && $advance < $promotion->minAdvanceDays => false,
            $promotion->ratePlanIds !== [] && ! in_array($stay->ratePlanId, $promotion->ratePlanIds, true) => false,
            $promotion->unitKeys !== [] && ! in_array($stay->unitKey, $promotion->unitKeys, true) => false,
            default => true,
        };
    }

    private function inWindow(string $date, ?string $from, ?string $to): bool
    {
        return ($from === null || $date >= $from) && ($to === null || $date <= $to);
    }
}
