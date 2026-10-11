<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\DTOs\ItemSummary;
use Modules\Inventory\Enums\UnitKind;
use Modules\Inventory\Exceptions\UnitNotConvertible;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemUnitConversion;
use Modules\Inventory\Models\Unit;

class ItemCatalogService implements ItemCatalog
{
    public function __construct(private readonly UnitConverter $converter) {}

    public function find(int $itemId): ?ItemSummary
    {
        $item = Item::query()->with(['category', 'baseUnit'])->find($itemId);

        return $item instanceof Item ? $this->summary($item) : null;
    }

    public function search(string $term, int $limit = 20): array
    {
        $term = trim($term);

        return Item::query()->with(['category', 'baseUnit'])->where('is_active', true)
            ->when($term !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', $term.'%')->orWhere('sku', 'like', $term.'%')->orWhere('barcode', $term)->orWhere('name', 'like', '% '.$term.'%')))
            ->orderBy('name')->limit($limit)->get()->map(fn (Item $item): ItemSummary => $this->summary($item))->all();
    }

    public function toBaseQuantity(int $itemId, string $quantity, int $unitId): string
    {
        $item = Item::query()->with('baseUnit')->findOrFail($itemId);

        if ($unitId === $item->base_unit_id) {
            return $quantity;
        }

        $own = ItemUnitConversion::query()->where('item_id', $item->id)->where('unit_id', $unitId)->first();

        if ($own instanceof ItemUnitConversion) {
            return $this->converter->viaItemFactor($quantity, (string) $own->factor);
        }

        $unit = Unit::query()->findOrFail($unitId);

        if ($unit->kind === UnitKind::Pack || $unit->kind !== $item->baseUnit->kind) {
            throw new UnitNotConvertible(__('Item :sku has no conversion from :unit to :base: add it on the item.', ['sku' => $item->sku, 'unit' => $unit->code, 'base' => $item->baseUnit->code]));
        }

        return $this->converter->between($quantity, ['kind' => $unit->kind, 'factor' => (string) $unit->factor], ['kind' => $item->baseUnit->kind, 'factor' => (string) $item->baseUnit->factor]);
    }

    private function summary(Item $item): ItemSummary
    {
        return new ItemSummary(
            $item->id, $item->sku, $item->name, $item->category->path(), $item->base_unit_id, $item->baseUnit->code, $item->is_stockable, $item->track_batches, $item->track_expiry,
            (string) $item->reorder_level, (string) $item->reorder_qty, $item->standard_cost !== null ? (string) $item->standard_cost : null, $item->is_active,
        );
    }
}
