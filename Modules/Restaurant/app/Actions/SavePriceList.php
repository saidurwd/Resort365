<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Restaurant\Events\MenuAvailabilityChanged;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\MenuSchedule;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;

/**
 * Saves an outlet's price list (ARCHITECTURE §8.4 outlet_menu_items) in one transaction: for each item
 * (and variant) of the property, whether the outlet sells it, its price, the outlet's station that
 * makes it, sold out (86), covered by meal plans, and its schedules. Rows not sold are removed.
 * Stations and schedules must be the outlet's; a variant must be the item's.
 */
class SavePriceList extends Action
{
    /**
     * @param  list<array{item_id: int, variant_id: int|null, on_sale: bool, price?: string|null, station_id?: int|null, is_available?: bool,
     *     is_package_eligible?: bool, schedule_ids?: list<int>}>  $rows
     * @return int how many rows the outlet now sells
     *
     * @throws RestaurantSetupInvalid
     */
    public function handle(Outlet $outlet, array $rows): int
    {
        $items = MenuItem::query()->where('property_id', $outlet->property_id)->with('variants')->get()->keyBy('id');
        $stations = KitchenStation::query()->where('outlet_id', $outlet->id)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $schedules = MenuSchedule::query()->where('outlet_id', $outlet->id)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($rows as $row) {
            $item = $items->get($row['item_id']);
            $variants = $item?->variants->pluck('id')->map(fn ($id): int => (int) $id)->all() ?? [];

            if ($item === null || ($row['variant_id'] === null ? $variants !== [] : ! in_array($row['variant_id'], $variants, true))) {
                throw new RestaurantSetupInvalid(__('A row of the price list does not match the menu; reload the page.'));
            }

            if ($row['on_sale'] && (($row['station_id'] ?? null) !== null && ! in_array($row['station_id'], $stations, true)
                || array_diff($row['schedule_ids'] ?? [], $schedules) !== [])) {
                throw new RestaurantSetupInvalid(__(':item: choose a station and schedules of this outlet.', ['item' => $item->translated('name')]));
            }

            if ($row['on_sale'] && ! is_numeric($row['price'] ?? null)) {
                throw new RestaurantSetupInvalid(__(':item needs a price.', ['item' => $item->translated('name')]));
            }
        }

        return $this->transaction(function () use ($outlet, $rows): int {
            $sold = 0;

            foreach ($rows as $row) {
                $key = ['outlet_id' => $outlet->id, 'menu_item_id' => $row['item_id'], 'variant_key' => $row['variant_id'] ?? 0];

                if (! $row['on_sale']) {
                    OutletMenuItem::query()->where($key)->delete();

                    continue;
                }

                $price = OutletMenuItem::query()->where($key)->first() ?? new OutletMenuItem(['property_id' => $outlet->property_id, ...$key]);
                $price->fill([
                    'menu_item_variant_id' => $row['variant_id'], 'price' => (string) BigDecimal::of((string) $row['price'])->toScale(2),
                    'kitchen_station_id' => $row['station_id'] ?? null, 'is_available' => $row['is_available'] ?? true,
                    'is_package_eligible' => $row['is_package_eligible'] ?? false, 'schedule_ids' => array_map(intval(...), $row['schedule_ids'] ?? []) ?: null,
                ])->save();
                $sold++;
            }

            MenuAvailabilityChanged::dispatch($outlet->tenant_id, $outlet->id);

            return $sold;
        });
    }
}
