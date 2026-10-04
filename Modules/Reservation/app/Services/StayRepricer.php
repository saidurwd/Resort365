<?php

namespace Modules\Reservation\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\DTOs\QuotedItem;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Models\ReservationItemNight;

/**
 * Changes to an in-house stay's nights (ARCHITECTURE §6.7): prices nights for an item (its rate
 * plan, BookingQuoter), adds or removes nightly snapshots and keeps the item's and the booking's
 * totals, balance and payment status in step. Nights already on a folio are never removed.
 * Called inside the caller's transaction.
 */
class StayRepricer
{
    public function __construct(
        private readonly BookingQuoter $quoter,
        private readonly ReservationBalance $balance,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * The price of an item's unit (or another room) for nights [from, to).
     */
    public function quote(Reservation $reservation, ReservationItem $item, CarbonImmutable $from, CarbonImmutable $to, ?int $roomId = null): QuotedItem
    {
        $unit = $item->item_type === ItemType::Room ? ($roomId ?? (int) $item->room_id) : $item->cottage_id;

        return $this->quoter->quote(new NewReservation(
            $reservation->property_id, $from, $to, [new BookingItem($item->item_type, $unit, $item->rate_plan_id, $item->adults, $item->children)],
            $reservation->primary_guest_id, $reservation->source, depositPercent: $reservation->deposit_percent, allowDepositOverride: true,
        ))->items[0];
    }

    /**
     * Adds a quoted item's nights to an existing item (extension, re-priced move).
     */
    public function addNights(ReservationItem $item, QuotedItem $line): void
    {
        $now = now();
        $meals = count($line->quote->nights) > 0 ? bcdiv($line->quote->mealComponent, (string) count($line->quote->nights), 2) : '0.00';

        foreach ($line->quote->nights as $night) {
            ReservationItemNight::query()->create([
                'property_id' => $item->property_id, 'reservation_item_id' => $item->id, 'stay_date' => $night->date, 'base_rate' => $night->base,
                'extra_person_amount' => $night->extras, 'meal_amount' => $meals, 'discount' => $night->discount, 'net_amount' => $night->net,
                'tax_amount' => $night->tax, 'total_amount' => $night->total, 'rate_source' => $night->source, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->shift($item, $line->quote->subtotal, $line->quote->discount, $line->quote->tax, $line->quote->total, $line->quote->mealComponent);
    }

    /**
     * Removes an item's nights on or after a date that are not on a folio yet.
     *
     * @return int how many nights were removed
     */
    public function removeNightsFrom(ReservationItem $item, string $fromDate): int
    {
        $nights = ReservationItemNight::query()->where('reservation_item_id', $item->id)->where('stay_date', '>=', $fromDate)->whereNull('posted_to_folio_at')->get();
        $sum = fn (string $field): string => (string) $nights->reduce(fn (BigDecimal $total, ReservationItemNight $night): BigDecimal => $total->plus($night->{$field}), BigDecimal::zero());

        $subtotal = (string) BigDecimal::of($sum('base_rate'))->plus($sum('extra_person_amount'));
        $this->shift($item, '-'.$subtotal, '-'.$sum('discount'), '-'.$sum('tax_amount'), '-'.$sum('total_amount'), '-'.$sum('meal_amount'));
        ReservationItemNight::query()->whereIn('id', $nights->pluck('id'))->delete();

        return $nights->count();
    }

    /**
     * Locks a room for nights [from, to) for an item. The unique (room_id, stay_date) index decides:
     * a clash throws UniqueConstraintViolationException and the caller's transaction rolls back.
     *
     * @param  list<int>  $roomIds
     */
    public function lock(ReservationItem $item, array $roomIds, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $now = now();
        $locks = [];

        for ($date = $from; $date->lt($to); $date = $date->addDay()) {
            foreach ($roomIds as $roomId) {
                $locks[] = [
                    'tenant_id' => $item->tenant_id, 'property_id' => $item->property_id, 'room_id' => $roomId, 'stay_date' => $date->toDateString(),
                    'lock_type' => LockType::Reservation->value, 'reservation_id' => $item->reservation_id, 'reservation_item_id' => $item->id,
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }

        if ($locks !== []) {
            InventoryLock::query()->insert($locks);
        }
    }

    /**
     * Recomputes the booking's totals from its items, then its balance and payment status.
     */
    public function refresh(Reservation $reservation): Reservation
    {
        $items = ReservationItem::query()->where('reservation_id', $reservation->id)->get();
        $sum = fn (string $field): string => (string) $items->reduce(fn (BigDecimal $total, ReservationItem $item): BigDecimal => $total->plus($item->{$field}), BigDecimal::zero())->toScale(2);
        $total = $sum('total');

        $reservation->forceFill([
            'subtotal' => $sum('subtotal'),
            'discount_total' => $sum('discount'),
            'tax_total' => $sum('tax'),
            'grand_total' => $total,
            'balance_due' => $this->balance->balance($total, $reservation->amount_paid),
            'payment_status' => $this->balance->status($total, $reservation->amount_paid, $reservation->deposit_required),
        ])->save();

        return $reservation;
    }

    /**
     * The property's business date ("tonight").
     */
    public function businessDate(int $propertyId): CarbonImmutable
    {
        return CarbonImmutable::parse($this->properties->find($propertyId)->businessDate ?? now()->toDateString());
    }

    private function shift(ReservationItem $item, string $subtotal, string $discount, string $tax, string $total, string $meals): void
    {
        $add = fn (string $current, string $delta): string => (string) BigDecimal::of($current)->plus($delta)->toScale(2);

        $item->forceFill([
            'subtotal' => $add($item->subtotal, $subtotal),
            'discount' => $add($item->discount, $discount),
            'tax' => $add($item->tax, $tax),
            'total' => $add($item->total, $total),
            'meal_component' => $add($item->meal_component, $meals),
        ])->save();
    }
}
