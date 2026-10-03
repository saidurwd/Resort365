<?php

namespace Modules\Reservation\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Core\Contracts\Settings;
use Modules\Core\Contracts\TaxEngine;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Property\Enums\UnitKind;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\NightlyRate;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Rates\DTOs\StayRequest;
use Modules\Reservation\DTOs\NightPrice;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\DTOs\PricedNight;
use Modules\Reservation\DTOs\PriceQuote;

/**
 * Prices a stay night by night (ARCHITECTURE §6.3): base rate (date price → season → base),
 * extra guests, the best promotion spread over the nights, then the rate plan's taxes per night
 * (inclusive or exclusive). Meals are included in the rate; mealComponent reports their part.
 */
class PricingService
{
    public function __construct(
        private readonly RateLookup $rates,
        private readonly InventoryCatalog $catalog,
        private readonly TaxEngine $taxes,
        private readonly Settings $settings,
        private readonly PriceCalculator $calculator,
    ) {}

    /**
     * One room of a room type.
     */
    public function quoteRoomType(int $ratePlanId, int $roomTypeId, Occupancy $occupancy, CarbonImmutable $checkIn, CarbonImmutable $checkOut, ?string $promoCode = null): ?PriceQuote
    {
        $plan = $this->rates->ratePlan($ratePlanId);
        $type = $this->catalog->find(UnitKind::RoomType, $roomTypeId);

        if (! $plan instanceof RatePlanSummary || ! $type instanceof UnitTypeSummary) {
            return null;
        }

        $rates = $this->rates->nightlyRates($plan->id, [$type->key()], $checkIn, $checkOut->subDay());

        return $this->priceRoomType($plan, $type, $rates[$type->key()] ?? [], $occupancy, $checkIn, $promoCode);
    }

    /**
     * A whole cottage.
     */
    public function quoteCottage(int $ratePlanId, int $cottageId, Occupancy $occupancy, CarbonImmutable $checkIn, CarbonImmutable $checkOut, ?string $promoCode = null): ?PriceQuote
    {
        $plan = $this->rates->ratePlan($ratePlanId);

        if (! $plan instanceof RatePlanSummary) {
            return null;
        }

        $cottage = collect($this->catalog->cottages($plan->propertyId))->firstWhere('id', $cottageId);

        if (! $cottage instanceof CottageSummary) {
            return null;
        }

        $rooms = array_values(array_filter($this->catalog->rooms($plan->propertyId), fn (RoomSummary $room): bool => in_array($room->id, $cottage->roomIds, true)));

        $keys = array_values(array_unique([$cottage->unitKey(), ...array_map(fn (RoomSummary $room): string => $room->unitKey(), $rooms)]));

        return $this->priceCottage($plan, $cottage, $rooms, $this->rates->nightlyRates($plan->id, $keys, $checkIn, $checkOut->subDay()), $occupancy, $checkIn, $promoCode);
    }

    /**
     * @param  array<string, NightlyRate|null>  $nightlyRates  the type's rate for each night of the stay
     */
    public function priceRoomType(RatePlanSummary $plan, UnitTypeSummary $type, array $nightlyRates, Occupancy $occupancy, CarbonImmutable $checkIn, ?string $promoCode, bool $fits = true): ?PriceQuote
    {
        $nights = $this->calculator->nights($nightlyRates, $type->baseOccupancy, $occupancy);

        return $nights === null ? null : $this->finish($plan, $type->key(), $occupancy, $nights, $promoCode, $fits);
    }

    /**
     * @param  list<RoomSummary>  $rooms  the cottage's active rooms
     * @param  array<string, array<string, NightlyRate|null>>  $nightlyRates  unit key => date => rate (cottage type and room types)
     */
    public function priceCottage(RatePlanSummary $plan, CottageSummary $cottage, array $rooms, array $nightlyRates, Occupancy $occupancy, CarbonImmutable $checkIn, ?string $promoCode, bool $fits = true): ?PriceQuote
    {
        $discount = (string) $this->settings->get('reservation.whole_cottage_discount_percent', $plan->propertyId);
        $nights = $this->calculator->cottageNights(
            $nightlyRates[$cottage->unitKey()] ?? [],
            array_map(fn (RoomSummary $room): array => $nightlyRates[$room->unitKey()] ?? [], $rooms),
            $discount !== '' ? $discount : '0',
        );

        return $nights === null ? null : $this->finish($plan, $cottage->unitKey(), $occupancy, $nights, $promoCode, $fits);
    }

    /**
     * Promotion, taxes and totals for priced nights.
     *
     * @param  list<NightPrice>  $nights
     */
    private function finish(RatePlanSummary $plan, string $unitKey, Occupancy $occupancy, array $nights, ?string $promoCode, bool $fits): PriceQuote
    {
        $amounts = array_map(fn (NightPrice $night): string => (string) BigDecimal::of($night->base)->plus($night->extras)->toScale(2), $nights);
        $nightly = array_combine(array_map(fn (NightPrice $night): string => $night->date, $nights), $amounts);
        $bookedOn = CarbonImmutable::now()->toDateString();

        $promotion = $this->rates->bestPromotion($plan->propertyId, new StayRequest($plan->id, $unitKey, $nightly, $bookedOn, $promoCode));
        $discounts = $this->calculator->spread($promotion->amount ?? '0', $amounts);

        $priced = [];
        $sum = ['subtotal' => BigDecimal::zero(), 'discount' => BigDecimal::zero(), 'net' => BigDecimal::zero(), 'tax' => BigDecimal::zero(), 'total' => BigDecimal::zero()];

        foreach ($nights as $i => $night) {
            $amount = BigDecimal::of($amounts[$i])->minus($discounts[$i]);
            $breakdown = $this->taxes->calculate((string) $amount, $plan->taxCategoryId, $plan->pricesIncludeTax);

            $priced[] = new PricedNight($night->date, $night->base, $night->extras, $discounts[$i], $breakdown->net, $breakdown->taxTotal, $breakdown->gross,
                $night->source, $night->seasonName);
            $sum['subtotal'] = $sum['subtotal']->plus($amounts[$i]);
            $sum['discount'] = $sum['discount']->plus($discounts[$i]);
            $sum['net'] = $sum['net']->plus($breakdown->net);
            $sum['tax'] = $sum['tax']->plus($breakdown->taxTotal);
            $sum['total'] = $sum['total']->plus($breakdown->gross);
        }

        $meals = BigDecimal::of($plan->mealAdultAmount)->multipliedBy($occupancy->adults)
            ->plus(BigDecimal::of($plan->mealChildAmount)->multipliedBy($occupancy->children))
            ->multipliedBy(count($nights));

        return new PriceQuote(
            $plan->id, $unitKey, $occupancy, $priced,
            (string) $sum['subtotal']->toScale(2), (string) $sum['discount']->toScale(2), (string) $sum['net']->toScale(2),
            (string) $sum['tax']->toScale(2), (string) $sum['total']->toScale(2), (string) $meals->toScale(2),
            $promotion instanceof PromotionDiscount && ! BigDecimal::of($promotion->amount)->isZero() ? $promotion : null,
            $fits,
        );
    }
}
