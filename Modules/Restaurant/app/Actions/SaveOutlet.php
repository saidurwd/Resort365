<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Models\Outlet;

/**
 * Creates or changes an outlet of a property.
 */
class SaveOutlet extends Action
{
    /**
     * @param  array<string, mixed>  $data  validated SaveOutletRequest fields
     */
    public function handle(int $propertyId, ?Outlet $outlet, array $data): Outlet
    {
        $outlet ??= new Outlet(['property_id' => $propertyId]);
        $outlet->fill([
            'code' => strtoupper((string) $data['code']), 'name' => $data['name'], 'type' => $data['type'],
            'prices_include_tax' => (bool) ($data['prices_include_tax'] ?? false), 'default_tax_category_id' => $data['default_tax_category_id'] ?? null,
            'bill_prefix' => strtoupper((string) $data['bill_prefix']), 'receipt_header' => $data['receipt_header'] ?? null, 'receipt_footer' => $data['receipt_footer'] ?? null,
            'opening_hours' => $data['opening_hours'] ?? null, 'is_active' => (bool) ($data['is_active'] ?? true), 'sort_order' => (int) ($data['sort_order'] ?? 0),
        ])->save();

        return $outlet;
    }
}
