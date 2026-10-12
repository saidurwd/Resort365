<?php

namespace Modules\Procurement\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\DTOs\ItemSummary;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorItem;

/**
 * Adds or changes an entry on a vendor's price list (Step 5.5): an item, its price per base unit, the smallest
 * quantity the price holds for and the dates it is valid.
 */
class SaveVendorPrice extends Action
{
    public function __construct(private readonly ItemCatalog $items) {}

    /**
     * @param  array{item_id: int, price: string, min_quantity?: string|null, vendor_sku?: string|null, valid_from?: string|null, valid_to?: string|null}  $data
     *
     * @throws ProcurementRuleViolated
     */
    public function handle(Vendor $vendor, ?VendorItem $entry, array $data): VendorItem
    {
        if (! $this->items->find((int) $data['item_id']) instanceof ItemSummary) {
            throw new ProcurementRuleViolated(__('Unknown item.'));
        }

        if (! BigDecimal::of($data['price'])->isPositive()) {
            throw new ProcurementRuleViolated(__('The price must be more than zero.'));
        }

        $from = ($data['valid_from'] ?? null) ?: null;
        $to = ($data['valid_to'] ?? null) ?: null;

        if ($from !== null && $to !== null && $to < $from) {
            throw new ProcurementRuleViolated(__('The price cannot end before it starts.'));
        }

        $values = [
            'item_id' => (int) $data['item_id'], 'vendor_sku' => ($data['vendor_sku'] ?? null) ?: null, 'price' => $data['price'], 'min_quantity' => ($data['min_quantity'] ?? null) ?: '0',
            'valid_from' => $from, 'valid_to' => $to,
        ];

        if ($entry instanceof VendorItem) {
            $entry->forceFill($values)->save();

            return $entry;
        }

        return VendorItem::query()->create(['vendor_id' => $vendor->id, ...$values]);
    }
}
