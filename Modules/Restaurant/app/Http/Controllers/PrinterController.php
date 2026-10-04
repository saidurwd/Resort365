<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Actions\SavePrinter;
use Modules\Restaurant\Enums\PrinterConnection;
use Modules\Restaurant\Enums\PrinterType;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Requests\PrinterRequest;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Printer;

/**
 * Restaurant → Printers: the current property's receipt and kitchen-ticket printers.
 */
class PrinterController extends Controller
{
    public function index(PropertyDirectory $properties): View
    {
        Gate::authorize('viewAny', Printer::class);
        $propertyId = $this->propertyId();

        return view('restaurant::printers.index', [
            'printers' => Printer::query()->where('property_id', $propertyId)->orderBy('type')->orderBy('name')->get(),
            'usedBy' => KitchenStation::query()->where('property_id', $propertyId)->whereNotNull('printer_id')->with('outlet')->get()->groupBy('printer_id'),
            'types' => PrinterType::options(),
            'connections' => PrinterConnection::options(),
            'propertyName' => $properties->find($propertyId)->name ?? '',
            'canManage' => auth()->user()?->can('create', Printer::class) ?? false,
        ]);
    }

    public function store(PrinterRequest $request, SavePrinter $save): RedirectResponse
    {
        return $this->save($request, null, $save);
    }

    public function update(PrinterRequest $request, Printer $printer, SavePrinter $save): RedirectResponse
    {
        return $this->save($request, $printer, $save);
    }

    public function destroy(Printer $printer): RedirectResponse
    {
        Gate::authorize('delete', $printer);
        $printer->delete();

        return to_route('restaurant.printers.index')->with('success', __('Printer removed; its stations show tickets on screen until another printer is chosen.'));
    }

    private function save(PrinterRequest $request, ?Printer $printer, SavePrinter $save): RedirectResponse
    {
        try {
            $save->handle($printer->property_id ?? $this->propertyId(), $printer, [
                'name' => (string) $request->validated('name'), 'type' => (string) $request->validated('type'), 'connection' => (string) $request->validated('connection'),
                'address' => $request->validated('address'), 'paper_width_mm' => (int) $request->validated('paper_width_mm'), 'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (RestaurantSetupInvalid $exception) {
            return to_route('restaurant.printers.index')->withInput()->with('error', $exception->getMessage());
        }

        return to_route('restaurant.printers.index')->with('success', __('Printer saved.'));
    }

    private function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }
}
