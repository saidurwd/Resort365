<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Enums\BlockStatus;
use Modules\Housekeeping\Enums\BlockType;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Models\RoomBlock;
use Modules\Reservation\Contracts\RoomBlocks;
use Modules\Reservation\Exceptions\RoomBlockRefused;

/**
 * Takes a room out of order (or out of service) for nights [from, to) (ARCHITECTURE §5.11). Out of
 * order locks every night in inventory through Reservation's RoomBlocks, so the room cannot be
 * sold; a room booked or in house on any of the nights is refused, naming the bookings, and nothing
 * is saved. Out of service only flags the room on the board.
 */
class BlockRoom extends Action
{
    public function __construct(
        private readonly RoomBlocks $locks,
    ) {}

    /**
     * @throws HousekeepingNotPossible
     */
    public function handle(int $propertyId, int $roomId, BlockType $type, string $from, string $to, string $reason, ?int $userId = null): RoomBlock
    {
        if ($to <= $from) {
            throw new HousekeepingNotPossible(__('The room must be back on a later date.'));
        }

        try {
            return $this->transaction(function () use ($propertyId, $roomId, $type, $from, $to, $reason, $userId): RoomBlock {
                $block = RoomBlock::query()->create([
                    'property_id' => $propertyId, 'room_id' => $roomId, 'type' => $type, 'from_date' => $from, 'to_date' => $to,
                    'reason' => $reason, 'status' => BlockStatus::Active, 'created_by' => $userId,
                ]);

                if ($type === BlockType::OutOfOrder) {
                    $this->locks->block($propertyId, $roomId, $from, $to, $block->id, $reason);
                }

                return $block;
            });
        } catch (RoomBlockRefused $exception) {
            throw new HousekeepingNotPossible($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
