<?php

namespace Modules\Property\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Property\Actions\DeleteAmenity;
use Modules\Property\Actions\SaveAmenity;
use Modules\Property\Http\Requests\SaveAmenityRequest;
use Modules\Property\Models\Amenity;

class AmenityController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Amenity::class);

        return view('property::amenities.index', [
            'amenities' => Amenity::query()->withCount(['cottageTypes', 'roomTypes'])->orderBy('category')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Amenity::class);

        return view('property::amenities.form', ['amenity' => null]);
    }

    public function store(SaveAmenityRequest $request, SaveAmenity $save): RedirectResponse
    {
        $amenity = $save->handle(null, $request->validated());

        return to_route('property.amenities.index')->with('success', __('Amenity ":name" created.', ['name' => $amenity->name]));
    }

    public function edit(Amenity $amenity): View
    {
        Gate::authorize('update', $amenity);

        return view('property::amenities.form', ['amenity' => $amenity]);
    }

    public function update(SaveAmenityRequest $request, Amenity $amenity, SaveAmenity $save): RedirectResponse
    {
        $save->handle($amenity, $request->validated());

        return to_route('property.amenities.index')->with('success', __('Amenity ":name" saved.', ['name' => $amenity->name]));
    }

    public function destroy(Amenity $amenity, DeleteAmenity $delete): RedirectResponse
    {
        Gate::authorize('delete', $amenity);
        $delete->handle($amenity);

        return to_route('property.amenities.index')->with('success', __('Amenity ":name" deleted.', ['name' => $amenity->name]));
    }
}
