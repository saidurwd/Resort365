<?php

namespace Modules\Property\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Property\Actions\DeleteRoom;
use Modules\Property\Actions\SaveRoom;
use Modules\Property\Exceptions\CannotDelete;
use Modules\Property\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Property\Http\Requests\SaveRoomRequest;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;
use Modules\Property\Services\RoomsTable;

class RoomController extends Controller
{
    use UsesCurrentProperty;

    public function index(): View
    {
        Gate::authorize('viewAny', Room::class);
        $this->currentPropertyId();

        return view('property::rooms.index', ['columns' => RoomsTable::columns(), 'propertyName' => $this->currentPropertyName()]);
    }

    public function data(RoomsTable $table): JsonResponse
    {
        Gate::authorize('viewAny', Room::class);

        return $table->toJson($this->currentPropertyId());
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Room::class);

        return view('property::rooms.form', $this->formData(null, $this->currentPropertyId()) + ['cottageId' => $request->integer('cottage') ?: null]);
    }

    public function store(SaveRoomRequest $request, SaveRoom $save): RedirectResponse
    {
        $room = $save->handle(null, $request->validated());

        return to_route('property.cottages.show', $room->cottage_id)->with('success', __('Room :number created.', ['number' => $room->number]));
    }

    public function edit(Room $room): View
    {
        Gate::authorize('update', $room);

        return view('property::rooms.form', $this->formData($room, $room->property_id) + ['cottageId' => $room->cottage_id, 'history' => app(AuditTrail::class)->for($room)]);
    }

    public function update(SaveRoomRequest $request, Room $room, SaveRoom $save): RedirectResponse
    {
        $save->handle($room, $request->validated());

        return to_route('property.cottages.show', $room->cottage_id)->with('success', __('Room :number saved.', ['number' => $room->number]));
    }

    public function destroy(Room $room, DeleteRoom $delete): RedirectResponse
    {
        Gate::authorize('delete', $room);

        try {
            $delete->handle($room);
        } catch (CannotDelete $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('property.cottages.show', $room->cottage_id)->with('success', __('Room :number deleted.', ['number' => $room->number]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Room $room, int $propertyId): array
    {
        return [
            'room' => $room,
            'cottages' => Cottage::query()->where('property_id', $propertyId)->orderBy('sort_order')->orderBy('name')->get()
                ->mapWithKeys(fn (Cottage $cottage): array => [$cottage->id => $cottage->code.' · '.$cottage->name])->all(),
            'roomTypes' => RoomType::query()->where('property_id', $propertyId)
                ->where(fn ($query) => $query->where('is_active', true)->when($room, fn ($query) => $query->orWhere('id', $room?->room_type_id)))
                ->orderBy('sort_order')->orderBy('name')->get()
                ->mapWithKeys(fn (RoomType $type): array => [$type->id => $type->name.' ('.__('max :count', ['count' => $type->max_occupancy]).')'])->all(),
        ];
    }
}
