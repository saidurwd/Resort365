<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Inventory\Enums\UnitKind;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\ItemStoreLevel;
use Modules\Inventory\Models\ItemUnitConversion;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;

/**
 * Saves an item with its own unit conversions and its par and reorder levels per store (Step 5.2). A blank SKU
 * becomes ITM-00001… The purchase and issue units must be convertible to the base unit: a unit of its kind, or
 * one with a conversion on this item (1 box = 24 pcs).
 */
class SaveItem extends Action
{
    public function __construct(private readonly DocumentNumbers $numbers) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{unit_id: int, factor: string}>  $conversions
     * @param  list<array{store_id: int, par_level: string|null, reorder_level: string|null}>  $levels
     *
     * @throws InventoryRuleViolated
     */
    public function handle(?Item $item, array $data, array $conversions = [], array $levels = []): Item
    {
        $base = Unit::query()->find($data['base_unit_id']) ?? throw new InventoryRuleViolated(__('Choose the base unit.'));
        ItemCategory::query()->find($data['item_category_id']) ?? throw new InventoryRuleViolated(__('Choose a category.'));
        $own = [];

        foreach ($conversions as $conversion) {
            $unit = Unit::query()->find($conversion['unit_id']) ?? throw new InventoryRuleViolated(__('Unknown unit in a conversion.'));

            if ($unit->id === $base->id || isset($own[$unit->id])) {
                throw new InventoryRuleViolated(__('A conversion is for a different unit than the base unit, once each (:unit).', ['unit' => $unit->code]));
            }

            if (! BigDecimal::of($conversion['factor'])->isPositive()) {
                throw new InventoryRuleViolated(__('The conversion of :unit must be more than zero.', ['unit' => $unit->code]));
            }

            $own[$unit->id] = (string) BigDecimal::of($conversion['factor'])->toScale(6);
        }

        foreach (['purchase_unit_id' => __('purchase'), 'issue_unit_id' => __('issue')] as $field => $label) {
            $id = $data[$field] ?? null;

            if ($id === null || (int) $id === $base->id || isset($own[(int) $id])) {
                continue;
            }

            $unit = Unit::query()->find($id) ?? throw new InventoryRuleViolated(__('Unknown :label unit.', ['label' => $label]));

            if ($unit->kind === UnitKind::Pack || $unit->kind !== $base->kind) {
                throw new InventoryRuleViolated(__('The :label unit :unit needs a conversion to :base: add it below.', ['label' => $label, 'unit' => $unit->code, 'base' => $base->code]));
            }
        }

        $stores = [];

        foreach ($levels as $level) {
            Store::query()->find($level['store_id']) ?? throw new InventoryRuleViolated(__('Unknown store in the levels.'));

            if (isset($stores[$level['store_id']])) {
                throw new InventoryRuleViolated(__('A store has one set of levels per item.'));
            }

            $stores[$level['store_id']] = $level;
        }

        return $this->transaction(function () use ($item, $data, $own, $stores): Item {
            $sku = trim((string) ($data['sku'] ?? ''));
            $sku = $sku !== '' ? mb_strtoupper($sku) : ($item instanceof Item ? $item->sku : $this->numbers->next('item'));
            $barcode = trim((string) ($data['barcode'] ?? ''));

            if (Item::query()->where('sku', $sku)->when($item instanceof Item, fn ($query) => $query->whereKeyNot($item?->id))->exists()) {
                throw new InventoryRuleViolated(__('The SKU :sku is already used.', ['sku' => $sku]));
            }

            if ($barcode !== '' && Item::query()->where('barcode', $barcode)->when($item instanceof Item, fn ($query) => $query->whereKeyNot($item?->id))->exists()) {
                throw new InventoryRuleViolated(__('The barcode :code belongs to another item.', ['code' => $barcode]));
            }

            $item ??= new Item;
            $item->fill([
                'item_category_id' => $data['item_category_id'], 'sku' => $sku, 'name' => trim((string) $data['name']), 'barcode' => $barcode !== '' ? $barcode : null, 'base_unit_id' => $data['base_unit_id'],
                'purchase_unit_id' => $data['purchase_unit_id'] ?? null, 'issue_unit_id' => $data['issue_unit_id'] ?? null, 'is_stockable' => (bool) ($data['is_stockable'] ?? true),
                'track_batches' => (bool) ($data['track_batches'] ?? false), 'track_expiry' => (bool) ($data['track_expiry'] ?? false), 'reorder_level' => $data['reorder_level'] ?? '0',
                'reorder_qty' => $data['reorder_qty'] ?? '0', 'standard_cost' => ($data['standard_cost'] ?? '') !== '' ? $data['standard_cost'] : null,
                'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null, 'is_active' => (bool) ($data['is_active'] ?? true),
            ])->save();

            ItemUnitConversion::query()->where('item_id', $item->id)->delete();

            foreach ($own as $unitId => $factor) {
                ItemUnitConversion::query()->create(['item_id' => $item->id, 'unit_id' => $unitId, 'factor' => $factor]);
            }

            ItemStoreLevel::query()->where('item_id', $item->id)->delete();

            foreach ($stores as $storeId => $level) {
                if ($level['par_level'] !== null || $level['reorder_level'] !== null) {
                    ItemStoreLevel::query()->create(['item_id' => $item->id, 'store_id' => $storeId, 'par_level' => $level['par_level'], 'reorder_level' => $level['reorder_level']]);
                }
            }

            return $item->load(['conversions', 'levels']);
        });
    }
}
