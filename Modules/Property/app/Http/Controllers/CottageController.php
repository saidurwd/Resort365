<?php

namespace Modules\Property\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Property\Actions\CreateCottageWithRooms;
use Modules\Property\Actions\DeleteCottage;
use Modules\Property\Actions\UpdateCottage;
use Modules\Property\Exceptions\CannotDelete;
use Modules\Property\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Property\Http\Requests\CreateCottageRequest;
use Modules\Property\Http\Requests\UpdateCottageRequest;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;
use Modules\Property\Services\CottagesTable;
use Modules\Property\Services\OccupancyCalculator;

class CottageController extends Controller
{
    use UsesCurrentProperty;

    public function index(): View
    {
        Gate::authorize('viewAny', Cottage::class);
        $this->currentPropertyId();

        return view('property::cottages.index', ['columns' => CottagesTable::columns(), 'propertyName' => $this->currentPropertyName()]);
    }

    public function data(CottagesTable $table): JsonResponse
    {
        Gate::authorize('viewAny', Cottage::class);

        return $table->toJson($this->currentPropertyId());
    }

    /**
     * The quick "add cottage with N rooms" form.
     */
    public function create(): View
    {
        Gate::authorize('create', Cottage::class);
        $propertyId = $this->currentPropertyId();

        return view('property::cottages.create', [
            'propertyName' => $this->currentPropertyName(),
            'cottageTypes' => $this->cottageTypeOptions($propertyId),
            'roomTypes' => RoomType::query()->where('property_id', $propertyId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function store(CreateCottageRequest $request, CreateCottageWithRooms $create): RedirectResponse
    {
        $cottage = $create->handle($request->validated());

        return to_route('property.cottages.show', $cottage)->with('success', trans_choice('Cottage ":name" created with :count room.|Cottage ":name" created with :count rooms.', $cottage->rooms()->count(), [
            'name' => $cottage->name,
        ]));
    }

    public function show(Cottage $cottage, OccupancyCalculator $occupancy): View
    {
        Gate::authorize('view', $cottage);
        $cottage->load(['cottageType', 'rooms.roomType']);

        return view('property::cottages.show', [
            'cottage' => $cottage,
            'rooms' => $cottage->rooms->map(fn (Room $room): array => ['room' => $room, 'capacity' => $occupancy->forRoom($room)]),
            'maxOccupancy' => $occupancy->forCottage($cottage),
            'roomsOccupancy' => $occupancy->cottage(null, $cottage->rooms->where('is_active', true)->map(fn (Room $room): int => $occupancy->forRoom($room)->maxOccupancy)),
            'history' => app(AuditTrail::class)->for($cottage),
        ]);
    }

    public function edit(Cottage $cottage): View
    {
        Gate::authorize('update', $cottage);

        return view('property::cottages.edit', ['cottage' => $cottage, 'cottageTypes' => $this->cottageTypeOptions($cottage->property_id)]);
    }

    public function update(UpdateCottageRequest $request, Cottage $cottage, UpdateCottage $update): RedirectResponse
    {
        $update->handle($cottage, $request->validated());

        return to_route('property.cottages.show', $cottage)->with('success', __('Cottage ":name" saved.', ['name' => $cottage->name]));
    }

    public function destroy(Cottage $cottage, DeleteCottage $delete): RedirectResponse
    {
        Gate::authorize('delete', $cottage);

        try {
            $delete->handle($cottage);
        } catch (CannotDelete $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('property.cottages.index')->with('success', __('Cottage ":name" deleted.', ['name' => $cottage->name]));
    }

    /**
     * @return array<int, string>
     */
    private function cottageTypeOptions(int $propertyId): array
    {
        return CottageType::query()->where('property_id', $propertyId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all();
    }
}
