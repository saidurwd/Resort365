<?php

namespace Modules\Property\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Property\Actions\DeleteRoomType;
use Modules\Property\Actions\SaveRoomType;
use Modules\Property\Exceptions\CannotDelete;
use Modules\Property\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Property\Http\Requests\SaveRoomTypeRequest;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\RoomType;

class RoomTypeController extends Controller
{
    use UsesCurrentProperty;

    public function index(): View
    {
        Gate::authorize('viewAny', RoomType::class);

        return view('property::room-types.index', [
            'propertyName' => $this->currentPropertyName(),
            'roomTypes' => RoomType::query()->where('property_id', $this->currentPropertyId())
                ->withCount('rooms')->with('amenities')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', RoomType::class);
        $this->currentPropertyId();

        return view('property::room-types.form', $this->formData(null));
    }

    public function store(SaveRoomTypeRequest $request, SaveRoomType $save): RedirectResponse
    {
        $roomType = $save->handle(null, $request->validated());

        return to_route('property.room-types.edit', $roomType)->with('success', __('Room type ":name" created. You can add photos now.', ['name' => $roomType->name]));
    }

    public function edit(RoomType $roomType): View
    {
        Gate::authorize('update', $roomType);

        return view('property::room-types.form', $this->formData($roomType) + ['history' => app(AuditTrail::class)->for($roomType)]);
    }

    public function update(SaveRoomTypeRequest $request, RoomType $roomType, SaveRoomType $save): RedirectResponse
    {
        $save->handle($roomType, $request->validated());

        return to_route('property.room-types.index')->with('success', __('Room type ":name" saved.', ['name' => $roomType->name]));
    }

    public function destroy(RoomType $roomType, DeleteRoomType $delete): RedirectResponse
    {
        Gate::authorize('delete', $roomType);

        try {
            $delete->handle($roomType);
        } catch (CannotDelete $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('property.room-types.index')->with('success', __('Room type ":name" deleted.', ['name' => $roomType->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?RoomType $roomType): array
    {
        return [
            'roomType' => $roomType,
            'amenities' => Amenity::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'selectedAmenities' => $roomType?->amenities->pluck('id')->all() ?? [],
        ];
    }
}
