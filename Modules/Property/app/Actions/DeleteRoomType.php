<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Exceptions\CannotDelete;
use Modules\Property\Models\RoomType;

/**
 * Deletes (soft) a room type that no room uses.
 */
class DeleteRoomType extends Action
{
    /**
     * @throws CannotDelete when rooms still use it
     */
    public function handle(RoomType $roomType): void
    {
        if ($roomType->rooms()->exists()) {
            throw CannotDelete::because(__('Rooms still use the type ":name". Change or delete them first, or deactivate the type.', ['name' => $roomType->name]));
        }

        $roomType->delete();
    }
}
