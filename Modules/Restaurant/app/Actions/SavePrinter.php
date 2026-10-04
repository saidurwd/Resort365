<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\PrinterConnection;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\Printer;

/**
 * Creates or changes a printer of a property. A network or agent printer needs its address.
 */
class SavePrinter extends Action
{
    /**
     * @param  array{name: string, type: string, connection: string, address?: string|null, paper_width_mm: int, is_active?: bool}  $data
     *
     * @throws RestaurantSetupInvalid
     */
    public function handle(int $propertyId, ?Printer $printer, array $data): Printer
    {
        if (PrinterConnection::from($data['connection']) !== PrinterConnection::Browser && trim((string) ($data['address'] ?? '')) === '') {
            throw new RestaurantSetupInvalid(__('A network or agent printer needs its address.'));
        }

        $printer ??= new Printer(['property_id' => $propertyId]);
        $printer->fill([
            'name' => $data['name'], 'type' => $data['type'], 'connection' => $data['connection'], 'address' => $data['address'] ?? null,
            'paper_width_mm' => $data['paper_width_mm'], 'is_active' => $data['is_active'] ?? true,
        ])->save();

        return $printer;
    }
}
