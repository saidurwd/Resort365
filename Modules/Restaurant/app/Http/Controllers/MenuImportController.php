<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Restaurant\Actions\ImportMenu;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Controllers\Concerns\CurrentProperty;
use Modules\Restaurant\Http\Requests\MenuImportRequest;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Services\MenuCsv;
use Modules\Restaurant\Services\MenuLanguages;

/**
 * Restaurant → Menu → Import: menu items from a CSV (all rows or none), and a template to start from.
 */
class MenuImportController extends Controller
{
    use CurrentProperty;

    public function create(): View
    {
        Gate::authorize('restaurant.menu.manage');

        return view('restaurant::menu.import', ['errorsList' => session('import_errors', [])]);
    }

    public function store(MenuImportRequest $request, ImportMenu $import): RedirectResponse
    {
        try {
            $counts = $import->handle($this->propertyId(), (string) file_get_contents((string) $request->file('file')?->getRealPath()));
        } catch (RestaurantSetupInvalid $exception) {
            return to_route('restaurant.menu.import')->with('error', __('Nothing was imported. Fix these lines and try again.'))
                ->with('import_errors', explode("\n", $exception->getMessage()));
        }

        return to_route('restaurant.menu.items.index')->with('success', __('Menu imported: :created new, :updated updated.', $counts));
    }

    public function template(MenuLanguages $languages): Response
    {
        Gate::authorize('restaurant.menu.manage');
        $codes = Outlet::query()->where('property_id', $this->propertyId())->orderBy('sort_order')->pluck('code')->all();
        $extra = array_keys(array_diff_key($languages->all(), ['en' => true]));
        $header = [...MenuCsv::COLUMNS, ...array_merge(...array_map(fn (string $lang): array => ['name_'.$lang, 'description_'.$lang], $extra)), ...array_map(fn (string $code): string => 'price:'.$code, $codes)];
        $example = ['CHK-CURRY', 'Chicken curry', 'Slow-cooked in onion gravy', 'Food > Bangladeshi', 'main', 'dish', 'FNB', 'halal|spicy', 'milk', 'Half|Full',
            ...array_fill(0, count($extra) * 2, ''), ...array_fill(0, count($codes), '350|600')];
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, $header, ',', '"', '');
        fputcsv($csv, $example, ',', '"', '');
        rewind($csv);

        return response((string) stream_get_contents($csv), 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="menu-template.csv"']);
    }
}
