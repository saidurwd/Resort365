<?php

namespace Modules\Restaurant\Services\Lookups;

use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Restaurant\Enums\RecipeKind;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\Recipe;

/**
 * Option lists of the recipe and wastage forms (id => label), read through Inventory's contracts.
 */
class RecipeOptions
{
    public function __construct(
        private readonly ItemCatalog $items,
        private readonly StoreDirectory $stores,
    ) {}

    /**
     * Stocked items as "name (unit)".
     *
     * @return array<int, string>
     */
    public function items(): array
    {
        $options = [];

        foreach ($this->items->search('', 500) as $item) {
            if ($item->isStockable) {
                $options[$item->id] = $item->name.' ('.$item->baseUnit.')';
            }
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function subRecipes(?int $exceptId = null): array
    {
        return Recipe::query()->where('kind', RecipeKind::Sub->value)->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))->orderBy('name')->get()
            ->mapWithKeys(fn (Recipe $recipe): array => [$recipe->id => $recipe->name.' ('.rtrim(rtrim((string) $recipe->yield_quantity, '0'), '.').' '.$recipe->yield_unit.')'])->all();
    }

    /**
     * @return array<int, string>
     */
    public function stores(int $propertyId): array
    {
        $options = [];

        foreach ($this->stores->forProperty($propertyId) as $store) {
            $options[$store->id] = $store->name;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function dishes(int $propertyId): array
    {
        return MenuItem::query()->where('property_id', $propertyId)->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn (MenuItem $item): array => [$item->id => $item->code.' · '.$item->translated('name')])->all();
    }
}
