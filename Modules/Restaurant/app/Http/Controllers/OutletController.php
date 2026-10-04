<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\TaxEngine;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Actions\SaveOutlet;
use Modules\Restaurant\Enums\OutletType;
use Modules\Restaurant\Enums\PrinterType;
use Modules\Restaurant\Enums\StationOutput;
use Modules\Restaurant\Enums\TableShape;
use Modules\Restaurant\Http\Requests\SaveOutletRequest;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\Printer;
use Modules\Restaurant\Services\FloorPlanGeometry;

/**
 * Restaurant → Outlets: the current property's outlets, and one outlet's setup page (details,
 * stations, terminals, floor plan).
 */
class OutletController extends Controller
{
    public const array WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public function index(PropertyDirectory $properties): View
    {
        Gate::authorize('viewAny', Outlet::class);
        $propertyId = $this->propertyId();

        return view('restaurant::outlets.index', [
            'outlets' => Outlet::query()->where('property_id', $propertyId)->withCount(['stations', 'terminals', 'tables'])->orderBy('sort_order')->orderBy('name')->get(),
            'propertyName' => $properties->find($propertyId)->name ?? '',
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Outlet::class);

        return $this->form(null);
    }

    public function store(SaveOutletRequest $request, SaveOutlet $save): RedirectResponse
    {
        $outlet = $save->handle($this->propertyId(), null, $request->validated());

        return to_route('restaurant.outlets.show', $outlet)->with('success', __('Outlet saved. Add its stations, terminals and floor plan below.'));
    }

    public function show(Outlet $outlet, PropertyDirectory $properties): View
    {
        Gate::authorize('view', $outlet);
        $outlet->load(['stations', 'terminals', 'areas.tables']);
        $printers = Printer::query()->where('property_id', $outlet->property_id)->where('is_active', true)->orderBy('name')->get();

        return view('restaurant::outlets.show', [
            'outlet' => $outlet,
            'propertyName' => $properties->find($outlet->property_id)->name ?? '',
            'kotPrinters' => $printers->where('type', PrinterType::Kot)->pluck('name', 'id')->all(),
            'receiptPrinters' => $printers->where('type', PrinterType::Receipt)->pluck('name', 'id')->all(),
            'printerNames' => Printer::query()->where('property_id', $outlet->property_id)->pluck('name', 'id')->all(),
            'outputs' => StationOutput::options(),
            'shapes' => TableShape::options(),
            'canvas' => ['width' => FloorPlanGeometry::WIDTH, 'height' => FloorPlanGeometry::HEIGHT, 'grid' => FloorPlanGeometry::GRID],
            'taxCategory' => $outlet->default_tax_category_id !== null ? ($this->taxCategories()[$outlet->default_tax_category_id] ?? null) : null,
            'canManage' => auth()->user()?->can('update', $outlet) ?? false,
            'canEditFloor' => auth()->user()?->can('editFloorPlan', $outlet) ?? false,
            'newToken' => session('terminal_token'),
        ]);
    }

    public function edit(Outlet $outlet): View
    {
        Gate::authorize('update', $outlet);

        return $this->form($outlet);
    }

    public function update(SaveOutletRequest $request, Outlet $outlet, SaveOutlet $save): RedirectResponse
    {
        $save->handle($outlet->property_id, $outlet, $request->validated());

        return to_route('restaurant.outlets.show', $outlet)->with('success', __('Outlet saved.'));
    }

    private function form(?Outlet $outlet): View
    {
        return view('restaurant::outlets.form', [
            'outlet' => $outlet,
            'types' => OutletType::options(),
            'taxCategories' => $this->taxCategories(),
            'weekdays' => self::WEEKDAYS,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function taxCategories(): array
    {
        $options = [];

        foreach (app(TaxEngine::class)->categories() as $category) {
            $options[$category->id] = $category->name;
        }

        return $options;
    }

    private function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }
}
