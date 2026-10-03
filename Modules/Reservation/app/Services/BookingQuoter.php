<?php

namespace Modules\Reservation\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\DTOs\PriceQuote;
use Modules\Reservation\DTOs\QuotedItem;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Exceptions\BookingNotPossible;

/**
 * Prices a whole booking — every item, the totals and the deposit — without saving anything.
 * The booking wizard shows it; CreateReservation saves exactly what it returns.
 *
 * The deposit uses the deposit policy of the first item's rate plan; the first night's total
 * (all items) is used by "first night" policies.
 */
class BookingQuoter
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly InventoryCatalog $catalog,
        private readonly PropertyDirectory $properties,
        private readonly RateLookup $rates,
    ) {}

    /**
     * @throws BookingNotPossible
     */
    public function quote(NewReservation $data): BookingQuote
    {
        if ($data->items === [] || $data->checkOut->lessThanOrEqualTo($data->checkIn)) {
            throw new BookingNotPossible(__('Choose at least one room or cottage, and a check-out after check-in.'));
        }

        $property = $this->properties->find($data->propertyId) ?? throw new BookingNotPossible(__('Unknown property.'));
        $items = $this->items($data);
        $sum = fn (string $field): string => (string) array_reduce($items, fn (BigDecimal $total, QuotedItem $item): BigDecimal => $total->plus($item->quote->{$field}), BigDecimal::zero())->toScale(2);
        $total = $sum('total');
        $firstNight = array_reduce($items, fn (BigDecimal $total, QuotedItem $item): BigDecimal => $total->plus($item->quote->nights[0]->total), BigDecimal::zero());
        $planId = $data->items[0]->ratePlanId;

        $deposit = $this->rates->depositQuote($planId, $total, (string) $firstNight, $data->depositPercent,
            CarbonImmutable::now($property->timezone), CarbonImmutable::parse($data->checkIn->toDateString().' '.$property->checkInTime, $property->timezone));

        return new BookingQuote(
            $items, $sum('subtotal'), $sum('discount'), $sum('tax'), $total, $deposit, $this->rates->depositPolicy($planId),
            $data->depositPercent === null || $this->rates->depositAllows($planId, $data->depositPercent),
            collect($items)->map(fn (QuotedItem $item): ?PromotionDiscount => $item->quote->promotion)->filter()->first(),
            $property->currencyCode,
        );
    }

    /**
     * @return list<QuotedItem>
     */
    private function items(NewReservation $data): array
    {
        $rooms = collect($this->catalog->rooms($data->propertyId))->keyBy('id');
        $cottages = collect($this->catalog->cottages($data->propertyId))->keyBy('id');
        $quoted = [];
        $seen = [];

        foreach ($data->items as $item) {
            $occupancy = new Occupancy($item->adults, $item->children);

            if ($item->type === ItemType::Room) {
                $room = $rooms->get($item->unitId);

                if (! $room instanceof RoomSummary || ! $room->isActive) {
                    throw new BookingNotPossible(__('This room cannot be booked.'));
                }

                [$label, $roomIds, $cottageId, $roomTypeId, $cottageTypeId] = [__('Room :number', ['number' => $room->number]), [$room->id], $room->cottageId, $room->roomTypeId, null];
                $quote = $this->pricing->quoteRoomType($item->ratePlanId, $room->roomTypeId, $occupancy, $data->checkIn, $data->checkOut, $data->promoCode);
            } else {
                $cottage = $cottages->get($item->unitId);

                if (! $cottage instanceof CottageSummary || ! $cottage->isActive || $cottage->roomIds === [] || ! $cottage->bookingMode->sellsWhole()) {
                    throw new BookingNotPossible(__('This cottage cannot be booked whole.'));
                }

                [$label, $roomIds, $cottageId, $roomTypeId, $cottageTypeId] = [$cottage->name, $cottage->roomIds, $cottage->id, null, $cottage->cottageTypeId];
                $quote = $this->pricing->quoteCottage($item->ratePlanId, $cottage->id, $occupancy, $data->checkIn, $data->checkOut, $data->promoCode);
            }

            if (! $quote instanceof PriceQuote) {
                throw new BookingNotPossible(__(':unit has no rate for these dates.', ['unit' => $label]));
            }

            if (array_intersect($roomIds, $seen) !== []) {
                throw new BookingNotPossible(__('A room is in this booking twice (:unit).', ['unit' => $label]));
            }

            $seen = [...$seen, ...$roomIds];
            $quoted[] = new QuotedItem($item, $quote, $label, $cottageId, $roomTypeId, $cottageTypeId, $roomIds);
        }

        return $quoted;
    }
}
