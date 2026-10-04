<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\ItemLabels;
use Modules\Reservation\Services\ReservationLogger;
use Modules\Reservation\Services\StayRepricer;

/**
 * Moves an in-house guest to another room (ARCHITECTURE §6.7 "room move"): the remaining nights,
 * from tonight to departure, are locked on the new room (any active room, in any cottage) and the
 * old room is free again from tonight; nights already passed stay on the old room. The rate is kept,
 * or — with reprice — the remaining nights not on a folio yet are priced for the new room. If
 * another booking holds the new room on any of those nights, nothing changes.
 */
class MoveRoom extends Action
{
    public function __construct(
        private readonly InventoryCatalog $catalog,
        private readonly StayRepricer $repricer,
        private readonly ItemLabels $labels,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @throws StayNotPossible
     */
    public function handle(ReservationItem $item, int $roomId, bool $reprice = false, ?int $userId = null): ReservationItem
    {
        if ($item->item_type !== ItemType::Room || $item->room_id === $roomId) {
            throw new StayNotPossible(__('Choose another room for this room.'));
        }

        $room = collect($this->catalog->rooms($item->property_id))->first(fn (RoomSummary $room): bool => $room->id === $roomId);

        if (! $room instanceof RoomSummary || ! $room->isActive) {
            throw new StayNotPossible(__('Choose an active room.'));
        }

        try {
            return $this->transaction(function () use ($item, $room, $reprice, $userId): ReservationItem {
                $locked = ReservationItem::query()->lockForUpdate()->findOrFail($item->id);
                $reservation = Reservation::query()->lockForUpdate()->findOrFail($locked->reservation_id);

                if ($locked->status !== ReservationStatus::CheckedIn) {
                    throw new StayNotPossible(__('Only a room in house can be moved; change the stay before arrival instead.'));
                }

                $tonight = $this->repricer->businessDate($locked->property_id);
                $from = $tonight->max($locked->check_in->toImmutable());
                $to = $locked->check_out->toImmutable();

                if ($from->gte($to)) {
                    throw new StayNotPossible(__('There are no nights left to move.'));
                }

                $before = $this->labels->of($locked);
                InventoryLock::query()->where('reservation_item_id', $locked->id)->where('stay_date', '>=', $from->toDateString())->delete();
                $locked->forceFill(['room_id' => $room->id, 'cottage_id' => $room->cottageId, 'room_type_id' => $room->roomTypeId])->save();
                $this->repricer->lock($locked, [$room->id], $from, $to);

                if ($reprice) {
                    $this->repricer->removeNightsFrom($locked, $from->toDateString());
                    $this->repricer->addNights($locked, $this->repricer->quote($reservation, $locked, $from, $to, $room->id));
                    $this->repricer->refresh($reservation);
                }

                $after = $this->labels->of($locked);
                $this->logger->log($reservation, ReservationLogAction::Modified, __('Moved from :from to :to from :date:rate.', [
                    'from' => $before, 'to' => $after, 'date' => $from->format('d M'), 'rate' => $reprice ? __(', at the new room\'s rate') : __(', same rate'),
                ]), ['room' => [$before, $after]], $userId);

                return $locked;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw new StayNotPossible(__('Room :number is taken on some of the remaining nights.', ['number' => $room->number]));
        }
    }
}
