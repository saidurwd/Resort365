<?php

namespace Modules\Reservation\Services;

use Carbon\CarbonImmutable;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Property\Enums\UnitKind;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;

/**
 * The tape chart of a property for a window of days (ARCHITECTURE §6.8): its cottages with their
 * rooms, and a bar for every booking or block on each room (TapeChartBuilder). A booking bar knows
 * its status, label and whether it may be dragged to another room.
 */
class TapeChart
{
    public function __construct(
        private readonly InventoryCatalog $catalog,
        private readonly GuestLookup $guests,
        private readonly TapeChartBuilder $builder,
    ) {}

    /**
     * @return array{days: list<CarbonImmutable>, cottages: list<array{cottage: CottageSummary, rooms: list<RoomSummary>}>, roomTypes: array<int, string>, bars: array<int, list<array<string, mixed>>>}
     */
    public function build(int $propertyId, CarbonImmutable $from, int $days): array
    {
        $to = $from->addDays($days - 1);
        $rooms = collect($this->catalog->rooms($propertyId))->filter(fn (RoomSummary $room): bool => $room->isActive)->sortBy('number', SORT_NATURAL)->groupBy('cottageId');

        $cottages = collect($this->catalog->cottages($propertyId))->filter(fn (CottageSummary $cottage): bool => $rooms->has($cottage->id))
            ->map(fn (CottageSummary $cottage): array => ['cottage' => $cottage, 'rooms' => $rooms->get($cottage->id)->values()->all()])->values()->all();

        $roomTypes = collect($this->catalog->unitTypes($propertyId))->filter(fn (UnitTypeSummary $type): bool => $type->kind === UnitKind::RoomType)
            ->mapWithKeys(fn (UnitTypeSummary $type): array => [$type->id => $type->code])->all();

        $locks = InventoryLock::query()->where('property_id', $propertyId)->whereBetween('stay_date', [$from->toDateString(), $to->toDateString()])->get();
        $reservations = Reservation::query()->whereIn('id', $locks->pluck('reservation_id')->filter()->unique())->get()->keyBy('id');
        $items = ReservationItem::query()->whereIn('id', $locks->pluck('reservation_item_id')->filter()->unique())->get()->keyBy('id');
        $names = $this->guests->names($reservations->pluck('primary_guest_id')->unique()->values()->all());

        $bars = $this->builder->bars($locks->map(fn (InventoryLock $lock): array => [
            'room_id' => $lock->room_id, 'date' => $lock->stay_date->toDateString(), 'type' => $lock->lock_type->value,
            'reservation_id' => $lock->reservation_id, 'item_id' => $lock->reservation_item_id, 'block' => $lock->note,
        ]), $from, $days);

        $byRoom = [];

        foreach ($bars as $bar) {
            $reservation = $bar['reservation_id'] !== null ? $reservations->get($bar['reservation_id']) : null;
            $item = $bar['item_id'] !== null ? $items->get($bar['item_id']) : null;

            $byRoom[$bar['room_id']][] = $bar + ($reservation instanceof Reservation ? [
                'code' => $reservation->code,
                'label' => $reservation->group_name ?? ($names[$reservation->primary_guest_id] ?? ''),
                'status' => $reservation->status,
                // A single room that is booked or in house can be dragged to another room.
                'draggable' => $item instanceof ReservationItem && $item->item_type === ItemType::Room
                    && in_array($item->status, [ReservationStatus::Tentative, ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true),
                'url' => route('reservation.bookings.show', $reservation),
            ] : [
                'code' => null,
                'label' => LockType::from($bar['type'])->label().($bar['block'] ? ' · '.$bar['block'] : ''),
                'status' => null,
                'draggable' => false,
                'url' => null,
            ]);
        }

        $dayList = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $dayList[] = $day;
        }

        return ['days' => $dayList, 'cottages' => $cottages, 'roomTypes' => $roomTypes, 'bars' => $byRoom];
    }
}
