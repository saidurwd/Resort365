<?php

namespace Modules\Reservation\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\DTOs\BookingQuote;
use Modules\Reservation\DTOs\QuotedItem;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItemNight;

/**
 * Writes a priced booking's items, nightly snapshots and room locks (CreateReservation,
 * ModifyReservation), inside the caller's transaction. Every room-night is locked in one bulk
 * insert, so the unique (room_id, stay_date) index decides who gets the rooms: when another
 * booking holds one, the insert throws UniqueConstraintViolationException and the caller's
 * transaction rolls back.
 */
class ReservationWriter
{
    public function __construct(private readonly InventoryCatalog $catalog) {}

    public function writeItems(Reservation $reservation, CarbonImmutable $checkIn, CarbonImmutable $checkOut, BookingQuote $quote): void
    {
        $now = now();
        $nights = [];
        $locks = [];

        foreach ($quote->items as $line) {
            $item = $this->createItem($reservation, $checkIn, $checkOut, $line);
            $meals = count($line->quote->nights) > 0 ? bcdiv($line->quote->mealComponent, (string) count($line->quote->nights), 2) : '0.00';

            foreach ($line->quote->nights as $night) {
                $nights[] = [
                    'tenant_id' => $reservation->tenant_id, 'property_id' => $reservation->property_id, 'reservation_item_id' => $item, 'stay_date' => $night->date,
                    'base_rate' => $night->base, 'extra_person_amount' => $night->extras, 'meal_amount' => $meals, 'discount' => $night->discount,
                    'net_amount' => $night->net, 'tax_amount' => $night->tax, 'total_amount' => $night->total, 'rate_source' => $night->source,
                    'created_at' => $now, 'updated_at' => $now,
                ];

                foreach ($line->roomIds as $roomId) {
                    $locks[] = [
                        'tenant_id' => $reservation->tenant_id, 'property_id' => $reservation->property_id, 'room_id' => $roomId, 'stay_date' => $night->date,
                        'lock_type' => LockType::Reservation->value, 'reservation_id' => $reservation->id, 'reservation_item_id' => $item,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
        }

        ReservationItemNight::query()->insert($nights);

        // One statement: the unique (room_id, stay_date) index decides who gets the rooms.
        InventoryLock::query()->insert($locks);
    }

    /**
     * The rooms and nights of a quote that another booking holds now (after a refused lock insert).
     *
     * @return array<string, list<string>>
     */
    public function takenRooms(int $propertyId, CarbonImmutable $checkIn, CarbonImmutable $checkOut, BookingQuote $quote, ?int $exceptReservationId = null): array
    {
        $roomIds = array_merge(...array_map(fn (QuotedItem $item): array => $item->roomIds, $quote->items));
        $dates = array_map(fn (CarbonInterface $date): string => $date->toDateString(), CarbonPeriod::create($checkIn, $checkOut->subDay())->toArray());
        $numbers = collect($this->catalog->rooms($propertyId))->mapWithKeys(fn (RoomSummary $room): array => [$room->id => $room->number]);

        $taken = InventoryLock::query()->whereIn('room_id', $roomIds)->whereIn('stay_date', $dates)
            ->when($exceptReservationId !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('reservation_id')->orWhere('reservation_id', '!=', $exceptReservationId)))
            ->orderBy('stay_date')->get()
            ->groupBy(fn (InventoryLock $lock): string => (string) $numbers->get($lock->room_id, '#'.$lock->room_id))
            ->map(fn ($locks): array => $locks->map(fn (InventoryLock $lock): string => $lock->stay_date->toDateString())->values()->all())
            ->all();

        return $taken !== [] ? $taken : ['?' => $dates];
    }

    /**
     * @return int the item id
     */
    private function createItem(Reservation $reservation, CarbonImmutable $checkIn, CarbonImmutable $checkOut, QuotedItem $line): int
    {
        return $reservation->items()->create([
            'property_id' => $reservation->property_id,
            'item_type' => $line->item->type,
            'cottage_id' => $line->cottageId,
            'room_id' => $line->item->type === ItemType::Room ? $line->item->unitId : null,
            'room_type_id' => $line->roomTypeId,
            'cottage_type_id' => $line->cottageTypeId,
            'rate_plan_id' => $line->item->ratePlanId,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'adults' => $line->item->adults,
            'children' => $line->item->children,
            'status' => $reservation->status,
            'subtotal' => $line->quote->subtotal,
            'discount' => $line->quote->discount,
            'tax' => $line->quote->tax,
            'total' => $line->quote->total,
            'meal_component' => $line->quote->mealComponent,
        ])->id;
    }
}
