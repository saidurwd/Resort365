<?php

namespace Modules\Housekeeping\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Housekeeping\Actions\BlockRoom;
use Modules\Housekeeping\Actions\EndBlock;
use Modules\Housekeeping\Enums\BlockStatus;
use Modules\Housekeeping\Enums\BlockType;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Http\Controllers\Concerns\HousekeepingScreen;
use Modules\Housekeeping\Http\Requests\BlockRoomRequest;
use Modules\Housekeeping\Models\RoomBlock;

/**
 * Housekeeping → Out of order: rooms taken out of order (off sale) or out of service, and putting
 * them back.
 */
class BlockController extends Controller
{
    use HousekeepingScreen;

    public function index(): View
    {
        Gate::authorize('viewAny', RoomBlock::class);
        $property = $this->property();

        return view('housekeeping::blocks.index', [
            'property' => $property,
            'active' => RoomBlock::query()->where('property_id', $property->id)->where('status', BlockStatus::Active->value)
                ->where('to_date', '>', $property->businessDate)->orderBy('from_date')->get(),
            'recent' => RoomBlock::query()->where('property_id', $property->id)
                ->where(fn ($query) => $query->where('status', BlockStatus::Ended->value)->orWhere('to_date', '<=', $property->businessDate))
                ->latest('updated_at')->limit(10)->get(),
            'rooms' => $this->roomOptions($property->id),
            'types' => BlockType::options(),
            'names' => $this->userNames(),
        ]);
    }

    public function store(BlockRoomRequest $request, BlockRoom $block): RedirectResponse
    {
        try {
            $block->handle($this->property()->id, (int) $request->validated('room_id'), BlockType::from((string) $request->validated('type')),
                (string) $request->validated('from_date'), (string) $request->validated('to_date'), (string) $request->validated('reason'), $this->userId());
        } catch (HousekeepingNotPossible $exception) {
            return to_route('housekeeping.blocks.index')->withInput()->with('error', $exception->getMessage());
        }

        return to_route('housekeeping.blocks.index')->with('success', __('Room blocked.'));
    }

    public function end(RoomBlock $block, EndBlock $end): RedirectResponse
    {
        Gate::authorize('update', $block);

        try {
            $end->handle($block, $this->userId());
        } catch (HousekeepingNotPossible $exception) {
            return to_route('housekeeping.blocks.index')->with('error', $exception->getMessage());
        }

        return to_route('housekeeping.blocks.index')->with('success', __('The room is back in service.'));
    }
}
