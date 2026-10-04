<?php

namespace Modules\Restaurant\Http\Controllers\Concerns;

use App\Support\Tenancy\PropertyContext;
use Modules\Core\Contracts\TaxEngine;
use Modules\Restaurant\Models\MenuCategory;

/**
 * Restaurant screens work on the current property.
 */
trait CurrentProperty
{
    protected function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    /**
     * Categories as indented options ("Food › Mains").
     *
     * @return array<int, string>
     */
    protected function categoryOptions(int $propertyId): array
    {
        $all = MenuCategory::query()->where('property_id', $propertyId)->orderBy('sort_order')->get();
        $options = [];
        $walk = function (?int $parentId, string $prefix) use (&$walk, &$options, $all): void {
            foreach ($all->where('parent_id', $parentId) as $category) {
                $options[$category->id] = $prefix.$category->translated('name');
                $walk($category->id, $prefix.$category->translated('name').' › ');
            }
        };
        $walk(null, '');

        return $options;
    }

    /**
     * @return array<int, string>
     */
    protected function taxCategoryOptions(): array
    {
        $options = [];

        foreach (app(TaxEngine::class)->categories() as $category) {
            $options[$category->id] = $category->name;
        }

        return $options;
    }
}
