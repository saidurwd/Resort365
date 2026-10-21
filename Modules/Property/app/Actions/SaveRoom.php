<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\PlanLimits;
use Modules\Property\Models\Room;

/**
 * Creates or updates a room (validated by SaveRoomRequest). A new room must fit the plan (Step 7.3).
 */
class SaveRoom extends Action
{
    public function __construct(private readonly PlanLimits $limits) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Room $room, array $data): Room
    {
        if (! $room instanceof Room) {
            $this->limits->ensureWithin('max_rooms', Room::query()->count() + 1);
        }

        $room ??= new Room;
        $room->fill($data)->save();

        return $room;
    }
}
