<?php

namespace Modules\Reservation\Services;

use Modules\Property\Contracts\InventoryCatalog;
use Modules\Reservation\Actions\ChangeItemRoom;
use Modules\Reservation\Actions\CheckInReservation;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\ReservationItem;

class StayOperationsService implements StayOperations
{
    public function __construct(
        private readonly CheckInReservation $checkIn,
        private readonly ChangeItemRoom $changeRoom,
        private readonly ReservationLookup $reservations,
        private readonly ItemLabels $labels,
        private readonly InventoryCatalog $catalog,
    ) {}

    public function checkIn(int $reservationId, ?int $userId = null): ReservationSummary
    {
        $this->checkIn->handle($reservationId, $userId);

        return $this->reservations->find($reservationId) ?? throw new StayNotPossible(__('Unknown reservation.'));
    }

    public function changeRoom(int $reservationItemId, int $roomId, ?int $userId = null): void
    {
        $this->changeRoom->handle(ReservationItem::query()->findOrFail($reservationItemId), $roomId, $userId);
    }

    public function roomItems(int $reservationId): array
    {
        $items = [];

        foreach (ReservationItem::query()->where('reservation_id', $reservationId)->orderBy('id')->get() as $item) {
            $items[$item->id] = ['label' => $this->labels->of($item), 'room_id' => $item->item_type === ItemType::Room ? $item->room_id : null, 'room_type_id' => $item->room_type_id];
        }

        return $items;
    }

    public function roomIds(int $reservationId): array
    {
        $ids = [];
        $cottages = null;

        foreach (ReservationItem::query()->where('reservation_id', $reservationId)->get() as $item) {
            if ($item->item_type === ItemType::Room && $item->room_id !== null) {
                $ids[] = $item->room_id;

                continue;
            }

            $cottages ??= collect($this->catalog->cottages($item->property_id))->keyBy('id');
            array_push($ids, ...($cottages->get($item->cottage_id)->roomIds ?? []));
        }

        return array_values(array_unique(array_map(intval(...), $ids)));
    }
}
