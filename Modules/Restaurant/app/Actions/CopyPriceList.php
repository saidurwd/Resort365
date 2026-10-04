<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;

/**
 * Starts an outlet's price list from another outlet's: every row it sells, with the price changed by a
 * percentage (e.g. +10 for the pool bar) and rounded to a whole number when asked. Stations are matched
 * by name in the target outlet (else none); schedules are not copied (they are per outlet). Rows the
 * target already sells are kept unless overwrite is on.
 *
 * @return int how many rows were copied
 */
class CopyPriceList extends Action
{
    /**
     * @throws RestaurantSetupInvalid
     */
    public function handle(Outlet $from, Outlet $to, string $percent = '0', bool $roundWhole = true, bool $overwrite = false): int
    {
        if ($from->id === $to->id || $from->property_id !== $to->property_id) {
            throw new RestaurantSetupInvalid(__('Choose another outlet of this resort to copy from.'));
        }

        $stationNames = KitchenStation::query()->where('outlet_id', $from->id)->pluck('name', 'id');
        $targetStations = KitchenStation::query()->where('outlet_id', $to->id)->pluck('id', 'name')->mapWithKeys(fn ($id, $name): array => [mb_strtolower((string) $name) => (int) $id]);
        $factor = BigDecimal::of(100)->plus($percent);

        return $this->transaction(function () use ($from, $to, $roundWhole, $overwrite, $stationNames, $targetStations, $factor): int {
            $copied = 0;

            foreach (OutletMenuItem::query()->where('outlet_id', $from->id)->get() as $row) {
                $key = ['outlet_id' => $to->id, 'menu_item_id' => $row->menu_item_id, 'variant_key' => $row->variant_key];
                $existing = OutletMenuItem::query()->where($key)->first();

                if ($existing instanceof OutletMenuItem && ! $overwrite) {
                    continue;
                }

                $price = BigDecimal::of($row->price)->multipliedBy($factor)->dividedBy(100, 2, RoundingMode::HalfUp);
                $station = $row->kitchen_station_id !== null ? ($targetStations[mb_strtolower((string) ($stationNames[$row->kitchen_station_id] ?? ''))] ?? null) : null;

                ($existing ?? new OutletMenuItem(['property_id' => $to->property_id, ...$key]))->fill([
                    'menu_item_variant_id' => $row->menu_item_variant_id,
                    'price' => (string) ($roundWhole ? $price->toScale(0, RoundingMode::HalfUp)->toScale(2) : $price),
                    'kitchen_station_id' => $station, 'is_available' => true, 'is_package_eligible' => $row->is_package_eligible, 'schedule_ids' => null,
                ])->save();
                $copied++;
            }

            return $copied;
        });
    }
}
