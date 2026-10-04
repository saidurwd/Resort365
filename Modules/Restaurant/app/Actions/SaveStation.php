<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\PrinterType;
use Modules\Restaurant\Enums\StationOutput;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\Printer;

/**
 * Creates or changes a kitchen station of an outlet (Q18): tickets go to its kitchen display, a
 * printer, or both; a printed station needs a kitchen-ticket printer of the same property.
 */
class SaveStation extends Action
{
    /**
     * @param  array{name: string, output: string, printer_id?: int|null, sort_order?: int}  $data
     *
     * @throws RestaurantSetupInvalid
     */
    public function handle(Outlet $outlet, ?KitchenStation $station, array $data): KitchenStation
    {
        $output = StationOutput::from($data['output']);
        $printerId = $output->needsPrinter() ? ($data['printer_id'] ?? null) : null;

        if ($output->needsPrinter()) {
            $printer = $printerId !== null ? Printer::query()->whereKey($printerId)->where('property_id', $outlet->property_id)->first() : null;

            if (! $printer instanceof Printer || $printer->type !== PrinterType::Kot) {
                throw new RestaurantSetupInvalid(__('Choose a kitchen-ticket printer for this station.'));
            }
        }

        $station ??= new KitchenStation(['property_id' => $outlet->property_id, 'outlet_id' => $outlet->id]);
        $station->fill(['name' => $data['name'], 'output' => $output, 'printer_id' => $printerId, 'sort_order' => $data['sort_order'] ?? $station->sort_order ?? 0])->save();

        return $station;
    }
}
