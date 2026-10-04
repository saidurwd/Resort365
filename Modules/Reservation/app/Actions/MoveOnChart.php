<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\StayRepricer;

/**
 * A booking dragged to another room on the tape chart (ARCHITECTURE §6.8), with the rules of the
 * stay actions: in house, the remaining nights move to any room at the same rate (MoveRoom); before
 * arrival, all nights move to a room of the same type at the same price (ChangeItemRoom). A room
 * held on any of those nights is refused, naming the nights; the unique lock index still decides
 * when two people drag at once. Whole cottages are not moved here.
 */
class MoveOnChart extends Action
{
    public function __construct(
        private readonly MoveRoom $moveInHouse,
        private readonly ChangeItemRoom $changeRoom,
        private readonly InventoryCatalog $catalog,
        private readonly StayRepricer $repricer,
    ) {}

    /**
     * @return string what happened, for the person who dragged it
     *
     * @throws StayNotPossible
     */
    public function handle(ReservationItem $item, int $roomId, ?int $userId = null): string
    {
        if ($item->item_type !== ItemType::Room) {
            throw new StayNotPossible(__('A whole cottage is moved with Change stay, not on the chart.'));
        }

        if (! in_array($item->status, [ReservationStatus::Tentative, ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true)) {
            throw new StayNotPossible(__('This booking can no longer be moved.'));
        }

        if ($item->room_id === $roomId) {
            throw new StayNotPossible(__('The booking is already in this room.'));
        }

        $room = collect($this->catalog->rooms($item->property_id))->first(fn (RoomSummary $room): bool => $room->id === $roomId);

        if (! $room instanceof RoomSummary || ! $room->isActive) {
            throw new StayNotPossible(__('Choose an active room.'));
        }

        $inHouse = $item->status === ReservationStatus::CheckedIn;

        if (! $inHouse && $room->roomTypeId !== $item->room_type_id) {
            throw new StayNotPossible(__('Room :number is another room type; change the room type with Modify booking, which re-prices it.', ['number' => $room->number]));
        }

        $from = $inHouse ? $this->repricer->businessDate($item->property_id)->max($item->check_in->toImmutable()) : $item->check_in->toImmutable();
        $this->refuseIfTaken($room, $item, $from, $item->check_out->toImmutable());

        if ($inHouse) {
            $this->moveInHouse->handle($item, $room->id, false, $userId);

            return __('Moved to room :number from :date, same rate.', ['number' => $room->number, 'date' => $from->format('d M')]);
        }

        $this->changeRoom->handle($item, $room->id, $userId);

        return __('Moved to room :number, same price.', ['number' => $room->number]);
    }

    /**
     * @throws StayNotPossible
     */
    private function refuseIfTaken(RoomSummary $room, ReservationItem $item, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $taken = InventoryLock::query()->where('room_id', $room->id)
            ->where('stay_date', '>=', $from->toDateString())->where('stay_date', '<', $to->toDateString())
            ->where(fn ($query) => $query->whereNull('reservation_item_id')->orWhere('reservation_item_id', '!=', $item->id))
            ->orderBy('stay_date')->get();

        if ($taken->isNotEmpty()) {
            throw new StayNotPossible(__('Room :number is taken on :dates.', [
                'number' => $room->number,
                'dates' => $taken->map(fn (InventoryLock $lock): string => $lock->stay_date->format('d M'))->implode(', '),
            ]));
        }
    }
}
