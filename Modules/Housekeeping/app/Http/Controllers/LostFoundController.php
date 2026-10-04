<?php

namespace Modules\Housekeeping\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Housekeeping\Actions\CloseFoundItem;
use Modules\Housekeeping\Actions\RecordFoundItem;
use Modules\Housekeeping\Enums\LostItemStatus;
use Modules\Housekeeping\Exceptions\HousekeepingNotPossible;
use Modules\Housekeeping\Http\Controllers\Concerns\HousekeepingScreen;
use Modules\Housekeeping\Http\Requests\CloseFoundItemRequest;
use Modules\Housekeeping\Http\Requests\FoundItemRequest;
use Modules\Housekeeping\Models\LostFoundItem;
use Modules\Housekeeping\Services\LostFoundTable;

/**
 * Housekeeping → Lost & found: the register of the current property, logging found items and
 * returning or disposing of them.
 */
class LostFoundController extends Controller
{
    use HousekeepingScreen;

    public function index(): View
    {
        Gate::authorize('viewAny', LostFoundItem::class);
        $property = $this->property();

        return view('housekeeping::lost-found.index', [
            'property' => $property,
            'columns' => LostFoundTable::columns(),
            'rooms' => $this->roomOptions($property->id),
            'canManage' => auth()->user()?->can('create', LostFoundItem::class) ?? false,
            'canFindGuests' => auth()->user()?->can('guest.guest.view') ?? false,
        ]);
    }

    public function data(LostFoundTable $table): JsonResponse
    {
        Gate::authorize('viewAny', LostFoundItem::class);

        return $table->toJson($this->property()->id, auth()->user()?->can('create', LostFoundItem::class) ?? false);
    }

    public function store(FoundItemRequest $request, RecordFoundItem $record): RedirectResponse
    {
        $record->handle($this->property()->id, [
            'found_on' => (string) $request->validated('found_on'),
            'room_id' => $request->filled('room_id') ? (int) $request->validated('room_id') : null,
            'found_at' => (string) $request->validated('found_at'),
            'description' => (string) $request->validated('description'),
            'stored_at' => $request->validated('stored_at'),
            'notes' => $request->validated('notes'),
        ], $this->userId());

        return to_route('housekeeping.lost-found.index')->with('success', __('Item logged.'));
    }

    public function close(CloseFoundItemRequest $request, LostFoundItem $item, CloseFoundItem $close): RedirectResponse
    {
        try {
            $close->handle($item, LostItemStatus::from((string) $request->validated('outcome')), $request->filled('guest_id') ? (int) $request->validated('guest_id') : null,
                $request->validated('claimed_by_name'), $request->validated('notes'), $this->userId());
        } catch (HousekeepingNotPossible $exception) {
            return to_route('housekeeping.lost-found.index')->with('error', $exception->getMessage());
        }

        return to_route('housekeeping.lost-found.index')->with('success', __('Lost & found entry closed.'));
    }
}
