<?php

namespace Modules\Reservation\Services;

use Carbon\CarbonImmutable;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\DTOs\PricedNight;
use Modules\Reservation\DTOs\PriceQuote;
use Modules\Reservation\DTOs\QuotedItem;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\QuoteItem;
use Modules\Reservation\Models\QuoteItemNight;

/**
 * Turns a saved quote back into the BookingQuote that CreateReservation books, with the quoted
 * nightly prices, discounts and taxes. Only the deposit is worked out again, at conversion time,
 * on the quoted total (its due time runs from now). A whole cottage locks the rooms it has today.
 */
class QuoteSnapshot
{
    public function __construct(
        private readonly InventoryCatalog $catalog,
        private readonly PropertyDirectory $properties,
        private readonly RateLookup $rates,
    ) {}

    /**
     * @throws BookingNotPossible when a quoted cottage no longer exists
     */
    public function bookingQuote(Quote $quote): BookingQuote
    {
        $quote->loadMissing('items.nights');
        $cottages = collect($this->catalog->cottages($quote->property_id))->keyBy('id');

        $items = $quote->items->map(function (QuoteItem $item) use ($cottages): QuotedItem {
            $roomIds = $item->item_type === ItemType::Room ? [(int) $item->room_id] : ($cottages->get($item->cottage_id)->roomIds ?? []);

            if ($roomIds === []) {
                throw new BookingNotPossible(__(':unit can no longer be booked.', ['unit' => $item->label]));
            }

            $promotion = $item->promotion_id !== null
                ? new PromotionDiscount($item->promotion_id, $item->promotion_code, (string) $item->promotion_name, (string) $item->promotion_amount, $item->nights->count())
                : null;

            $nights = $item->nights->map(fn (QuoteItemNight $night): PricedNight => new PricedNight(
                $night->stay_date->toDateString(), $night->base_rate, $night->extra_person_amount, $night->discount,
                $night->net_amount, $night->tax_amount, $night->total_amount, (string) $night->rate_source, $night->season_name,
            ))->values()->all();

            return new QuotedItem(
                $this->bookingItem($item),
                new PriceQuote($item->rate_plan_id, $item->unit_key, new Occupancy($item->adults, $item->children), $nights,
                    $item->subtotal, $item->discount, bcsub($item->subtotal, $item->discount, 2), $item->tax, $item->total, $item->meal_component, $promotion),
                $item->label, $item->cottage_id, $item->room_type_id, $item->cottage_type_id, array_map(intval(...), $roomIds),
            );
        })->values()->all();

        $property = $this->properties->find($quote->property_id) ?? throw new BookingNotPossible(__('Unknown property.'));
        $firstNight = array_reduce($items, fn (string $sum, QuotedItem $item): string => bcadd($sum, $item->quote->nights[0]->total ?? '0', 2), '0');

        $deposit = $this->rates->depositQuote($quote->rate_plan_id, $quote->grand_total, $firstNight, $quote->deposit_percent,
            CarbonImmutable::now($property->timezone), CarbonImmutable::parse($quote->check_in->toDateString().' '.$property->checkInTime, $property->timezone));

        return new BookingQuote(
            $items, $quote->subtotal, $quote->discount_total, $quote->tax_total, $quote->grand_total, $deposit,
            $this->rates->depositPolicy($quote->rate_plan_id), true,
            collect($items)->map(fn (QuotedItem $item): ?PromotionDiscount => $item->quote->promotion)->filter()->first(),
            $quote->currency_code,
        );
    }

    /**
     * @return list<BookingItem>
     */
    public function bookingItems(Quote $quote): array
    {
        return $quote->items->map(fn (QuoteItem $item): BookingItem => $this->bookingItem($item))->values()->all();
    }

    private function bookingItem(QuoteItem $item): BookingItem
    {
        return new BookingItem($item->item_type, $item->item_type === ItemType::Room ? (int) $item->room_id : $item->cottage_id,
            $item->rate_plan_id, $item->adults, $item->children);
    }
}
