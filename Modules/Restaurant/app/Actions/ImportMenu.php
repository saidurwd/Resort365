<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Core\Contracts\TaxEngine;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\MenuCategory;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\OutletMenuItem;
use Modules\Restaurant\Services\MenuCsv;
use Modules\Restaurant\Services\MenuLanguages;

/**
 * Imports menu items from a CSV (MenuCsv): all rows or none. Items are matched by code (updated) or
 * created; categories are created from their path ("Food > Mains") when missing; outlet prices come
 * from the price:<OUTLET> columns (a row's empty price column leaves that outlet's price as it is).
 * Variants, modifier groups and combo components are kept for existing items when the CSV has none.
 */
class ImportMenu extends Action
{
    public function __construct(
        private readonly MenuCsv $csv,
        private readonly MenuLanguages $languages,
        private readonly SaveMenuItem $save,
        private readonly TaxEngine $taxes,
    ) {}

    /**
     * @return array{created: int, updated: int}
     *
     * @throws RestaurantSetupInvalid with every problem, one per line
     */
    public function handle(int $propertyId, string $contents): array
    {
        $outlets = Outlet::query()->where('property_id', $propertyId)->get()->keyBy(fn (Outlet $outlet): string => strtoupper($outlet->code));
        $parsed = $this->csv->parse($contents, $outlets->keys()->all(), array_keys($this->languages->all()));
        $taxCategories = collect($this->taxes->categories())->mapWithKeys(fn ($category): array => [strtoupper($category->code) => $category->id]);
        $errors = $parsed['errors'];

        foreach ($parsed['rows'] as $row) {
            if ($row['tax_category'] !== null && ! $taxCategories->has($row['tax_category'])) {
                $errors[] = __('Line :line: tax category ":code" is unknown.', ['line' => $row['line'], 'code' => $row['tax_category']]);
            }
        }

        if ($errors !== []) {
            throw new RestaurantSetupInvalid(implode("\n", $errors));
        }

        return $this->transaction(function () use ($propertyId, $parsed, $outlets, $taxCategories): array {
            $counts = ['created' => 0, 'updated' => 0];

            foreach ($parsed['rows'] as $row) {
                $item = MenuItem::query()->where('property_id', $propertyId)->where('code', $row['code'])->with(['variants', 'modifierGroups', 'components'])->first();
                $counts[$item instanceof MenuItem ? 'updated' : 'created']++;

                $item = $this->save->handle($propertyId, $item, [
                    'menu_category_id' => $this->category($propertyId, $row['category'])->id, 'code' => $row['code'], 'name' => $row['name'],
                    'description' => $row['description'], 'course' => $row['course'], 'kind' => $row['kind'],
                    'tax_category_id' => $row['tax_category'] !== null ? $taxCategories[$row['tax_category']] : $item?->tax_category_id,
                    'dietary_tags' => $row['dietary_tags'], 'allergens' => $row['allergens'],
                    'variants' => $row['variants'] !== [] ? $row['variants'] : ($item?->variants->pluck('name')->all() ?? []),
                    'modifier_group_ids' => $item?->modifierGroups->pluck('id')->map(fn ($id): int => (int) $id)->all() ?? [],
                    'components' => $item?->components->map(fn ($component): array => ['item_id' => $component->component_item_id,
                        'variant_id' => $component->component_variant_id, 'quantity' => $component->quantity])->all() ?? [],
                ]);
                $variants = $item->variants()->get()->values();

                foreach ($row['prices'] as $code => $amounts) {
                    $outlet = $outlets->get($code);

                    foreach ($amounts as $index => $amount) {
                        $variant = $variants->get($index);
                        $key = ['outlet_id' => $outlet->id, 'menu_item_id' => $item->id, 'variant_key' => $variant->id ?? 0];
                        (OutletMenuItem::query()->where($key)->first() ?? new OutletMenuItem(['property_id' => $propertyId, ...$key, 'is_available' => true]))
                            ->fill(['menu_item_variant_id' => $variant?->id, 'price' => $amount])->save();
                    }
                }
            }

            return $counts;
        });
    }

    /**
     * The category at a path, created level by level when missing.
     *
     * @param  list<string>  $path
     */
    private function category(int $propertyId, array $path): MenuCategory
    {
        $parent = null;

        foreach ($path as $name) {
            $parent = MenuCategory::query()->where('property_id', $propertyId)->where('parent_id', $parent?->id)->get()
                ->first(fn (MenuCategory $category): bool => mb_strtolower($category->translated('name', 'en')) === mb_strtolower($name))
                ?? MenuCategory::query()->create(['property_id' => $propertyId, 'parent_id' => $parent?->id, 'name' => ['en' => $name], 'colour' => 'primary']);
        }

        return $parent ?? throw new RestaurantSetupInvalid(__('A category is missing.'));
    }
}
