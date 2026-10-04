<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\ItemLabels;
use Modules\Reservation\Services\ReservationLogger;

/**
 * Gives a room item another room of the same type at the same price (room confirmation at
 * check-in): in one transaction its locks move to the new room; if another booking holds the new
 * room on any of the nights, the unique lock index refuses it and nothing changes.
 */
class ChangeItemRoom extends Action
{
    public function __construct(
        private readonly InventoryCatalog $catalog,
        private readonly ItemLabels $labels,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @throws StayNotPossible
     */
    public function handle(ReservationItem $item, int $roomId, ?int $userId = null): ReservationItem
    {
        if ($item->item_type !== ItemType::Room || $item->room_id === $roomId) {
            throw new StayNotPossible(__('Only a single room can be changed here, and to a different room.'));
        }

        $room = collect($this->catalog->rooms($item->property_id))->first(fn (RoomSummary $room): bool => $room->id === $roomId);

        if (! $room instanceof RoomSummary || ! $room->isActive || $room->roomTypeId !== $item->room_type_id) {
            throw new StayNotPossible(__('Choose an active room of the same type.'));
        }

        try {
            return $this->transaction(function () use ($item, $room, $userId): ReservationItem {
                $locked = ReservationItem::query()->lockForUpdate()->findOrFail($item->id);
                $before = $this->labels->of($locked);
                $nights = InventoryLock::query()->where('reservation_item_id', $locked->id)->where('room_id', $locked->room_id)->pluck('stay_date');

                InventoryLock::query()->where('reservation_item_id', $locked->id)->delete();
                $now = now();
                InventoryLock::query()->insert($nights->map(fn ($date): array => [
                    'tenant_id' => $locked->tenant_id, 'property_id' => $locked->property_id, 'room_id' => $room->id, 'stay_date' => $date->toDateString(),
                    'lock_type' => LockType::Reservation->value, 'reservation_id' => $locked->reservation_id, 'reservation_item_id' => $locked->id,
                    'created_at' => $now, 'updated_at' => $now,
                ])->all());

                $locked->forceFill(['room_id' => $room->id, 'cottage_id' => $room->cottageId])->save();
                $reservation = Reservation::query()->findOrFail($locked->reservation_id);
                $this->logger->log($reservation, ReservationLogAction::Modified, __(':from changed to :to (same price).', ['from' => $before, 'to' => $this->labels->of($locked)]),
                    ['room' => [$before, $this->labels->of($locked)]], $userId);

                return $locked;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw new StayNotPossible(__('Room :number is taken on some of these nights.', ['number' => $room->number]));
        }
    }
}
