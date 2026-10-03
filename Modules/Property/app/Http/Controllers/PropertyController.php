<?php

namespace Modules\Property\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\ReferenceData;
use Modules\Property\Actions\SaveProperty;
use Modules\Property\Http\Requests\SavePropertyRequest;
use Modules\Property\Models\Property;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PropertyController extends Controller
{
    public function __construct(private readonly ReferenceData $reference) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Property::class);

        return view('property::properties.index', ['properties' => Property::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Property::class);

        return view('property::properties.form', $this->formData(null));
    }

    public function store(SavePropertyRequest $request, SaveProperty $saveProperty): RedirectResponse
    {
        $property = $saveProperty->handle(null, $request->safe()->except('logo'), $request->file('logo'));

        return to_route('property.properties.index')->with('success', __('Property ":name" created.', ['name' => $property->name]));
    }

    public function edit(Property $property): View
    {
        Gate::authorize('update', $property);

        return view('property::properties.form', $this->formData($property) + ['history' => app(AuditTrail::class)->for($property)]);
    }

    public function update(SavePropertyRequest $request, Property $property, SaveProperty $saveProperty): RedirectResponse
    {
        $saveProperty->handle($property, $request->safe()->except('logo'), $request->file('logo'));

        return to_route('property.properties.index')->with('success', __('Property ":name" saved.', ['name' => $property->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Property $property): array
    {
        return [
            'property' => $property,
            'countries' => $this->reference->countries(),
            'timezones' => $this->reference->timezones(),
            'currencies' => $this->reference->currencies(),
            'logoUrl' => $property?->getFirstMedia(Property::LOGO) instanceof Media ? route('core.attachments.show', $property->getFirstMedia(Property::LOGO)) : null,
        ];
    }
}
