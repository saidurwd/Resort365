<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Enums\LostItemStatus;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Models\LostFoundItem;

/**
 * Closes a lost & found entry: returned to its owner (a guest profile or a named person) or
 * disposed of. A closed entry does not change again.
 */
class CloseFoundItem extends Action
{
    /**
     * @throws HousekeepingNotPossible
     */
    public function handle(LostFoundItem $item, LostItemStatus $outcome, ?int $guestId, ?string $claimedBy, ?string $notes, ?int $userId = null): LostFoundItem
    {
        if ($item->status !== LostItemStatus::Stored || $outcome === LostItemStatus::Stored) {
            throw new HousekeepingNotPossible(__('This item is already :status.', ['status' => strtolower($item->status->label())]));
        }

        if ($outcome === LostItemStatus::Claimed && $guestId === null && trim((string) $claimedBy) === '') {
            throw new HousekeepingNotPossible(__('Say who the item was returned to.'));
        }

        $item->forceFill([
            'status' => $outcome, 'guest_id' => $outcome === LostItemStatus::Claimed ? $guestId : null,
            'claimed_by_name' => $outcome === LostItemStatus::Claimed && trim((string) $claimedBy) !== '' ? trim((string) $claimedBy) : null,
            'notes' => trim((string) $notes) !== '' ? trim((string) $notes) : $item->notes, 'closed_at' => now(), 'closed_by' => $userId,
        ])->save();

        return $item;
    }
}
