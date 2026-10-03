<?php

namespace Modules\Property\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Property\Actions\DeleteCottageType;
use Modules\Property\Actions\SaveCottageType;
use Modules\Property\Exceptions\CannotDelete;
use Modules\Property\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Property\Http\Requests\SaveCottageTypeRequest;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\CottageType;

class CottageTypeController extends Controller
{
    use UsesCurrentProperty;

    public function index(): View
    {
        Gate::authorize('viewAny', CottageType::class);

        return view('property::cottage-types.index', [
            'propertyName' => $this->currentPropertyName(),
            'cottageTypes' => CottageType::query()->where('property_id', $this->currentPropertyId())
                ->withCount('cottages')->with('amenities')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', CottageType::class);
        $this->currentPropertyId();

        return view('property::cottage-types.form', $this->formData(null));
    }

    public function store(SaveCottageTypeRequest $request, SaveCottageType $save): RedirectResponse
    {
        $cottageType = $save->handle(null, $request->validated());

        return to_route('property.cottage-types.edit', $cottageType)->with('success', __('Cottage type ":name" created. You can add photos now.', ['name' => $cottageType->name]));
    }

    public function edit(CottageType $cottageType): View
    {
        Gate::authorize('update', $cottageType);

        return view('property::cottage-types.form', $this->formData($cottageType) + ['history' => app(AuditTrail::class)->for($cottageType)]);
    }

    public function update(SaveCottageTypeRequest $request, CottageType $cottageType, SaveCottageType $save): RedirectResponse
    {
        $save->handle($cottageType, $request->validated());

        return to_route('property.cottage-types.index')->with('success', __('Cottage type ":name" saved.', ['name' => $cottageType->name]));
    }

    public function destroy(CottageType $cottageType, DeleteCottageType $delete): RedirectResponse
    {
        Gate::authorize('delete', $cottageType);

        try {
            $delete->handle($cottageType);
        } catch (CannotDelete $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('property.cottage-types.index')->with('success', __('Cottage type ":name" deleted.', ['name' => $cottageType->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?CottageType $cottageType): array
    {
        return [
            'cottageType' => $cottageType,
            'amenities' => Amenity::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'selectedAmenities' => $cottageType?->amenities->pluck('id')->all() ?? [],
        ];
    }
}
