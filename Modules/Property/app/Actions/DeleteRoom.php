<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Contracts\RoomUsage;
use Modules\Property\Exceptions\CannotDelete;
use Modules\Property\Models\Room;

/**
 * Deletes (soft) a room without future bookings (ARCHITECTURE §5.4). A room with bookings can
 * only be deactivated after they are moved.
 */
class DeleteRoom extends Action
{
    public function __construct(private readonly RoomUsage $usage) {}

    /**
     * @throws CannotDelete when the room has future bookings
     */
    public function handle(Room $room): void
    {
        if ($this->usage->hasFutureBookings($room->id)) {
            throw CannotDelete::because(__('Room :number has future bookings. Move them first, or deactivate the room.', ['number' => $room->number]));
        }

        $room->delete();
    }
}
