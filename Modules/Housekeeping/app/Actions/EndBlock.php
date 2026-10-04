<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Enums\BlockStatus;
use Modules\Housekeeping\Enums\BlockType;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Models\RoomBlock;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\RoomBlocks;

/**
 * Puts a blocked room back in service from the business date: the block ends there (to_date) and
 * its remaining out-of-order nights are released, so the room can be sold again. A block that had
 * not started yet is cancelled whole.
 */
class EndBlock extends Action
{
    public function __construct(
        private readonly RoomBlocks $locks,
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * @throws HousekeepingNotPossible
     */
    public function handle(RoomBlock $block, ?int $userId = null): RoomBlock
    {
        return $this->transaction(function () use ($block, $userId): RoomBlock {
            $locked = RoomBlock::query()->lockForUpdate()->findOrFail($block->id);

            if ($locked->status !== BlockStatus::Active) {
                throw new HousekeepingNotPossible(__('This block has already ended.'));
            }

            $today = $this->properties->find($locked->property_id)->businessDate ?? now()->toDateString();
            $end = max($today, $locked->from_date->toDateString());

            if ($locked->type === BlockType::OutOfOrder) {
                $this->locks->release($locked->id, $end);
            }

            $locked->forceFill([
                'status' => BlockStatus::Ended, 'to_date' => min($end, $locked->to_date->toDateString()), 'ended_by' => $userId, 'ended_at' => now(),
            ])->save();

            return $locked;
        });
    }
}
