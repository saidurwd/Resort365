<?php

namespace Modules\Housekeeping\Services;

use Modules\Housekeeping\Enums\BlockStatus;
use Modules\Housekeeping\Enums\TaskStatus;
use Modules\Housekeeping\Models\HousekeepingTask;
use Modules\Housekeeping\Models\RoomBlock;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\RoomStatuses;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Property\Enums\HousekeepingStatus;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\RoomOccupancy;

/**
 * The room status board of a property on its business date (ARCHITECTURE §5.11, §10 Room Status
 * Board): each active room, by cottage, with its cleaning status, whether it is occupied, arriving
 * or leaving (Reservation), an out-of-order or out-of-service block, and today's open task.
 */
class RoomBoard
{
    public function __construct(
        private readonly InventoryCatalog $catalog,
        private readonly RoomStatuses $statuses,
        private readonly ReservationLookup $reservations,
    ) {}

    /**
     * @return array{cottages: list<array{cottage: CottageSummary, rooms: list<array{room: RoomSummary, status: HousekeepingStatus, occupancy: RoomOccupancy|null, occupied: bool, block: RoomBlock|null, task: HousekeepingTask|null}>}>,
     *     counts: array<string, int>}
     */
    public function build(int $propertyId, string $date): array
    {
        $rooms = collect($this->catalog->rooms($propertyId))->filter(fn (RoomSummary $room): bool => $room->isActive)->sortBy('number', SORT_NATURAL)->groupBy('cottageId');
        $statuses = $this->statuses->forProperty($propertyId);
        $occupancy = $this->reservations->roomOccupancy($propertyId, $date);
        $blocks = RoomBlock::query()->where('property_id', $propertyId)->where('status', BlockStatus::Active->value)
            ->where('from_date', '<=', $date)->where('to_date', '>', $date)->get()->keyBy('room_id');
        $tasks = HousekeepingTask::query()->where('property_id', $propertyId)->where('business_date', $date)
            ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value, TaskStatus::Done->value])->orderBy('id')->get()->keyBy('room_id');
        $counts = ['clean' => 0, 'dirty' => 0, 'inspected' => 0, 'occupied' => 0, 'vacant' => 0, 'blocked' => 0];

        $cottages = collect($this->catalog->cottages($propertyId))->filter(fn (CottageSummary $cottage): bool => $rooms->has($cottage->id))
            ->map(function (CottageSummary $cottage) use ($rooms, $statuses, $occupancy, $blocks, $tasks, &$counts): array {
                return ['cottage' => $cottage, 'rooms' => $rooms->get($cottage->id)->map(function (RoomSummary $room) use ($statuses, $occupancy, $blocks, $tasks, &$counts): array {
                    $status = $statuses[$room->id] ?? HousekeepingStatus::Clean;
                    // A guest due out today is still in the room until checked out.
                    $occupied = isset($occupancy[$room->id]) && ($occupancy[$room->id]->occupied || $occupancy[$room->id]->departing);
                    $counts[$status->value]++;
                    $counts[$occupied ? 'occupied' : 'vacant']++;
                    $counts['blocked'] += $blocks->has($room->id) ? 1 : 0;

                    return ['room' => $room, 'status' => $status, 'occupancy' => $occupancy[$room->id] ?? null, 'occupied' => $occupied, 'block' => $blocks->get($room->id), 'task' => $tasks->get($room->id)];
                })->values()->all()];
            })->values()->all();

        return ['cottages' => $cottages, 'counts' => $counts];
    }
}
