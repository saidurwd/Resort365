<?php

namespace Modules\Housekeeping\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Housekeeping\Actions\SetRoomStatus;
use Modules\Housekeeping\Http\Controllers\Concerns\HousekeepingScreen;
use Modules\Housekeeping\Http\Requests\SetRoomStatusRequest;
use Modules\Housekeeping\Services\RoomBoard;
use Modules\Property\Enums\HousekeepingStatus;

/**
 * Housekeeping → Room status: the board of the current property on its business date, and the bulk
 * status update. Authorized by the housekeeping.board.view and housekeeping.room.update route
 * middleware (and SetRoomStatusRequest).
 */
class BoardController extends Controller
{
    use HousekeepingScreen;

    public function index(RoomBoard $board): View
    {
        $property = $this->property();

        return view('housekeeping::board.index', $board->build($property->id, $property->businessDate) + [
            'property' => $property,
            'statuses' => HousekeepingStatus::options(),
            'canUpdate' => auth()->user()?->can('housekeeping.room.update') ?? false,
        ]);
    }

    public function status(SetRoomStatusRequest $request, SetRoomStatus $set): RedirectResponse
    {
        $status = HousekeepingStatus::from((string) $request->validated('status'));
        $changed = $set->handle($this->property()->id, array_map(intval(...), (array) $request->validated('room_ids')), $status, $this->userId());

        return to_route('housekeeping.board')->with('success', trans_choice(':count room set to :status.|:count rooms set to :status.', $changed, ['status' => strtolower($status->label())]));
    }
}
