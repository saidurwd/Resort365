<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Models\Room;

/**
 * Creates or updates a room (validated by SaveRoomRequest).
 */
class SaveRoom extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Room $room, array $data): Room
    {
        $room ??= new Room;
        $room->fill($data)->save();

        return $room;
    }
}
