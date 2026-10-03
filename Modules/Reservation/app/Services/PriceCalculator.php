<?php

namespace Modules\Reservation\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Rates\DTOs\NightlyRate;
use Modules\Reservation\DTOs\NightPrice;
use Modules\Reservation\DTOs\Occupancy;

/**
 * Night-by-night price arithmetic (ARCHITECTURE §6.3), without database access.
 *
 * - The rate covers the type's base occupancy: adults fill it first, then children; guests
 *   beyond it pay the extra-adult / extra-child amounts of the night's rate.
 * - A whole cottage without a cottage-type rate for a night costs the sum of its rooms' rates,
 *   less the whole-cottage discount.
 * - A stay discount is spread over the nights in proportion to their amounts (the rounding
 *   difference goes to the last night), so night amounts always add up.
 */
class PriceCalculator
{
    /**
     * @return array{int, int} extra adults, extra children
     */
    public function extraGuests(int $baseOccupancy, Occupancy $occupancy): array
    {
        $extraAdults = max(0, $occupancy->adults - $baseOccupancy);
        $placesLeft = max(0, $baseOccupancy - $occupancy->adults);

        return [$extraAdults, max(0, $occupancy->children - $placesLeft)];
    }

    /**
     * Nights of a stay for one rate (null when any night has no price).
     *
     * @param  array<string, NightlyRate|null>  $rates  each night of the stay, in order
     * @return list<NightPrice>|null
     */
    public function nights(array $rates, int $baseOccupancy, Occupancy $occupancy): ?array
    {
        [$extraAdults, $extraChildren] = $this->extraGuests($baseOccupancy, $occupancy);
        $nights = [];

        foreach ($rates as $date => $rate) {
            if (! $rate instanceof NightlyRate) {
                return null;
            }

            $extras = BigDecimal::of($rate->extraAdultAmount)->multipliedBy($extraAdults)
                ->plus(BigDecimal::of($rate->extraChildAmount)->multipliedBy($extraChildren));
            $nights[] = new NightPrice($date, (string) BigDecimal::of($rate->amount)->toScale(2), (string) $extras->toScale(2),
                $rate->source->value, $rate->seasonName);
        }

        return $nights;
    }

    /**
     * A whole cottage's nights: its cottage-type rate where set, else the sum of its rooms' rates
     * less $discountPercent. Null when a night has neither.
     *
     * @param  array<string, NightlyRate|null>  $cottageRates
     * @param  list<array<string, NightlyRate|null>>  $roomRates  one entry per active room
     * @return list<NightPrice>|null
     */
    public function cottageNights(array $cottageRates, array $roomRates, string $discountPercent): ?array
    {
        $nights = [];
        $keep = BigDecimal::of(100)->minus($discountPercent);

        foreach ($cottageRates as $date => $rate) {
            if ($rate instanceof NightlyRate) {
                $nights[] = new NightPrice($date, (string) BigDecimal::of($rate->amount)->toScale(2), '0.00', $rate->source->value, $rate->seasonName);

                continue;
            }

            if ($roomRates === []) {
                return null;
            }

            $sum = BigDecimal::zero();
            $season = null;

            foreach ($roomRates as $rates) {
                $room = $rates[$date] ?? null;

                if (! $room instanceof NightlyRate) {
                    return null;
                }

                $sum = $sum->plus($room->amount);
                $season ??= $room->seasonName;
            }

            $nights[] = new NightPrice($date, (string) $sum->multipliedBy($keep)->dividedBy(100, 2, RoundingMode::HalfUp), '0.00', 'rooms', $season);
        }

        return $nights;
    }

    /**
     * Spreads a stay discount over amounts in proportion; the parts add up to the discount exactly.
     *
     * @param  list<string>  $amounts
     * @return list<string>
     */
    public function spread(string $discount, array $amounts): array
    {
        $total = array_reduce($amounts, fn (BigDecimal $sum, string $amount): BigDecimal => $sum->plus($amount), BigDecimal::zero());
        $discount = BigDecimal::min(BigDecimal::of($discount), $total);

        if ($amounts === [] || $total->isZero() || $discount->isZero()) {
            return array_map(fn (): string => '0.00', $amounts);
        }

        $parts = [];
        $given = BigDecimal::zero();
        $last = count($amounts) - 1;

        foreach ($amounts as $i => $amount) {
            $part = $i === $last
                ? $discount->minus($given)
                : $discount->multipliedBy($amount)->dividedBy($total, 2, RoundingMode::HalfUp);
            $given = $given->plus($part);
            $parts[] = (string) $part->toScale(2);
        }

        return $parts;
    }
}
