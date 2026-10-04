<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Restaurant\Actions\CopyPriceList;
use Modules\Restaurant\Actions\MarkSoldOut;
use Modules\Restaurant\Actions\SaveMenuSchedule;
use Modules\Restaurant\Actions\SavePriceList;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Requests\CopyPriceListRequest;
use Modules\Restaurant\Http\Requests\MenuScheduleRequest;
use Modules\Restaurant\Http\Requests\PriceListRequest;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuSchedule;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;

/**
 * An outlet's price list (Restaurant → Outlets → Price list): every item and variant of the menu with
 * this outlet's price, station, schedules, 86 and meal-plan flags; copying another outlet's prices;
 * the outlet's menu schedules; and the 86 switch the kitchen and cashiers use.
 */
class PriceListController extends Controller
{
    public function show(Outlet $outlet): View
    {
        Gate::authorize('viewPrices', $outlet);
        $items = MenuItem::query()->where('property_id', $outlet->property_id)->with(['category', 'variants'])->orderBy('menu_category_id')->orderBy('sort_order')->orderBy('code')->get();
        $prices = OutletMenuItem::query()->where('outlet_id', $outlet->id)->get()->keyBy(fn (OutletMenuItem $row): string => $row->menu_item_id.':'.$row->variant_key);
        $rows = [];

        foreach ($items as $item) {
            foreach ($item->variants->isEmpty() ? [null] : $item->variants->all() as $variant) {
                $price = $prices->get($item->id.':'.($variant->id ?? 0));
                $rows[] = [
                    'item_id' => $item->id, 'variant_id' => $variant?->id, 'row_id' => $price?->id, 'code' => $item->code, 'name' => $item->translated('name'),
                    'variant' => $variant?->name, 'category' => $item->category?->translated('name') ?? '', 'kind' => $item->kind->value, 'active' => $item->is_active,
                    'on_sale' => $price instanceof OutletMenuItem, 'price' => $price->price ?? '', 'station_id' => $price?->kitchen_station_id,
                    'is_available' => $price->is_available ?? true, 'is_package_eligible' => $price->is_package_eligible ?? false,
                    'schedule_ids' => $price->schedule_ids ?? [],
                ];
            }
        }

        return view('restaurant::menu.price-list', [
            'outlet' => $outlet,
            'rows' => $rows,
            'stations' => $outlet->stations()->pluck('name', 'id')->all(),
            'schedules' => MenuSchedule::query()->where('outlet_id', $outlet->id)->orderBy('start_time')->get(),
            'otherOutlets' => Outlet::query()->where('property_id', $outlet->property_id)->whereKeyNot($outlet->id)->orderBy('name')->pluck('name', 'id')->all(),
            'canPrice' => auth()->user()?->can('restaurant.price.manage') ?? false,
            'canSoldOut' => auth()->user()?->can('markSoldOut', $outlet) ?? false,
        ]);
    }

    public function update(PriceListRequest $request, Outlet $outlet, SavePriceList $save): RedirectResponse
    {
        try {
            $sold = $save->handle($outlet, array_values(array_map(fn (array $row): array => [
                'item_id' => (int) $row['item_id'], 'variant_id' => isset($row['variant_id']) ? (int) $row['variant_id'] : null, 'on_sale' => (bool) $row['on_sale'],
                'price' => isset($row['price']) ? (string) $row['price'] : null, 'station_id' => isset($row['station_id']) ? (int) $row['station_id'] : null,
                'is_available' => (bool) ($row['is_available'] ?? true), 'is_package_eligible' => (bool) ($row['is_package_eligible'] ?? false),
                'schedule_ids' => array_values(array_map(intval(...), (array) ($row['schedule_ids'] ?? []))),
            ], (array) $request->validated('rows'))));
        } catch (RestaurantSetupInvalid $exception) {
            return to_route('restaurant.outlets.prices', $outlet)->with('error', $exception->getMessage());
        }

        return to_route('restaurant.outlets.prices', $outlet)->with('success', trans_choice('Price list saved: :count item on sale.|Price list saved: :count items on sale.', $sold));
    }

    public function copy(CopyPriceListRequest $request, Outlet $outlet, CopyPriceList $copy): RedirectResponse
    {
        try {
            $copied = $copy->handle(Outlet::query()->findOrFail((int) $request->validated('from_outlet_id')), $outlet, (string) ($request->validated('percent') ?? '0'),
                $request->boolean('round_whole'), $request->boolean('overwrite'));
        } catch (RestaurantSetupInvalid $exception) {
            return to_route('restaurant.outlets.prices', $outlet)->with('error', $exception->getMessage());
        }

        return to_route('restaurant.outlets.prices', $outlet)->with('success', trans_choice(':count price copied.|:count prices copied.', $copied));
    }

    public function soldOut(Outlet $outlet, OutletMenuItem $price, MarkSoldOut $mark): JsonResponse
    {
        Gate::authorize('markSoldOut', $outlet);
        $row = $mark->handle($price, (bool) request()->boolean('sold_out'));

        return response()->json(['ok' => true, 'sold_out' => ! $row->is_available]);
    }

    public function storeSchedule(MenuScheduleRequest $request, Outlet $outlet, SaveMenuSchedule $save): RedirectResponse
    {
        $save->handle($outlet, null, $this->schedule($request));

        return redirect()->to(route('restaurant.outlets.prices', $outlet).'#schedules')->with('success', __('Schedule saved.'));
    }

    public function updateSchedule(MenuScheduleRequest $request, Outlet $outlet, MenuSchedule $schedule, SaveMenuSchedule $save): RedirectResponse
    {
        $save->handle($outlet, $schedule, $this->schedule($request));

        return redirect()->to(route('restaurant.outlets.prices', $outlet).'#schedules')->with('success', __('Schedule saved.'));
    }

    /**
     * @return array{name: string, days_of_week: list<string>, start_time: string, end_time: string, price_adjustment_percent: string, is_active: bool}
     */
    private function schedule(MenuScheduleRequest $request): array
    {
        return [
            'name' => (string) $request->validated('name'), 'days_of_week' => array_values((array) $request->validated('days_of_week')),
            'start_time' => (string) $request->validated('start_time'), 'end_time' => (string) $request->validated('end_time'),
            'price_adjustment_percent' => (string) ($request->validated('price_adjustment_percent') ?? '0'), 'is_active' => $request->boolean('is_active', true),
        ];
    }
}
