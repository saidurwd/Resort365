<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\Unit;
use Modules\Inventory\Services\ItemCsv;

/**
 * Imports an item list (Step 5.2), all or nothing. An item whose SKU exists is updated (its conversions and levels
 * stay), a new SKU is created; categories are found by name or path ("Food > Dairy") and created when missing; units
 * are found by code and must exist.
 */
class ImportItems extends Action
{
    public function __construct(private readonly ItemCsv $csv) {}

    /**
     * @return array{created: int, updated: int}
     *
     * @throws InventoryRuleViolated
     */
    public function handle(string $contents): array
    {
        $parsed = $this->csv->parse($contents);
        $units = Unit::query()->get()->keyBy(fn (Unit $unit): string => mb_strtolower($unit->code));
        $errors = $parsed['errors'];

        foreach ($parsed['rows'] as $index => $row) {
            $number = $index + 2;

            foreach (['base_unit', 'purchase_unit', 'issue_unit'] as $field) {
                if ($row[$field] !== null && ! $units->has(mb_strtolower($row[$field]))) {
                    $errors[] = __('Item :sku: the unit ":unit" does not exist.', ['sku' => $row['sku'], 'unit' => $row[$field]]);
                }
            }

            if ($row['barcode'] !== null && Item::query()->where('barcode', $row['barcode'])->where('sku', '!=', mb_strtoupper($row['sku']))->exists()) {
                $errors[] = __('Item :sku: the barcode :code belongs to another item.', ['sku' => $row['sku'], 'code' => $row['barcode']]);
            }
        }

        if ($errors !== []) {
            throw new InventoryRuleViolated(implode(' ', array_slice($errors, 0, 6)).(count($errors) > 6 ? ' '.__('And :n more.', ['n' => count($errors) - 6]) : ''));
        }

        return $this->transaction(function () use ($parsed, $units): array {
            $created = 0;
            $updated = 0;
            $categories = [];

            foreach ($parsed['rows'] as $row) {
                $categoryId = $categories[$row['category']] ??= $this->category($row['category']);
                $sku = mb_strtoupper($row['sku']);
                $item = Item::query()->where('sku', $sku)->first();
                $item instanceof Item ? $updated++ : $created++;
                $item ??= new Item;
                $item->fill([
                    'item_category_id' => $categoryId, 'sku' => $sku, 'name' => $row['name'], 'barcode' => $row['barcode'], 'base_unit_id' => $units[mb_strtolower($row['base_unit'])]->id,
                    'purchase_unit_id' => $row['purchase_unit'] !== null ? $units[mb_strtolower($row['purchase_unit'])]->id : null,
                    'issue_unit_id' => $row['issue_unit'] !== null ? $units[mb_strtolower($row['issue_unit'])]->id : null, 'is_stockable' => $row['stockable'], 'track_batches' => $row['batches'],
                    'track_expiry' => $row['expiry'], 'reorder_level' => $row['reorder_level'], 'reorder_qty' => $row['reorder_qty'], 'standard_cost' => $row['standard_cost'],
                    'description' => $row['description'], 'is_active' => true,
                ])->save();
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }

    private function category(string $path): int
    {
        $parentId = null;

        foreach (array_filter(array_map(trim(...), explode('>', $path)), fn (string $name): bool => $name !== '') as $name) {
            $category = ItemCategory::query()->where('name', $name)->where('parent_id', $parentId)->first()
                ?? ItemCategory::query()->create(['name' => $name, 'parent_id' => $parentId, 'sort_order' => 0, 'is_active' => true]);
            $parentId = $category->id;
        }

        return (int) $parentId;
    }
}
