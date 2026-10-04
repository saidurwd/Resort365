<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\MenuCategory;

/**
 * Creates or changes a menu category of a property; a category cannot be put under itself or one of
 * its own subcategories.
 */
class SaveMenuCategory extends Action
{
    /**
     * @param  array{name: array<string, string|null>, parent_id?: int|null, colour?: string, sort_order?: int, is_active?: bool}  $data
     *
     * @throws RestaurantSetupInvalid
     */
    public function handle(int $propertyId, ?MenuCategory $category, array $data): MenuCategory
    {
        $parentId = $data['parent_id'] ?? null;

        if ($category instanceof MenuCategory && $parentId !== null) {
            for ($id = $parentId; $id !== null; $id = MenuCategory::query()->whereKey($id)->value('parent_id')) {
                if ((int) $id === $category->id) {
                    throw new RestaurantSetupInvalid(__('A category cannot be inside itself.'));
                }
            }
        }

        $category ??= new MenuCategory(['property_id' => $propertyId]);
        $category->fill([
            'name' => array_filter($data['name'], fn (?string $value): bool => trim((string) $value) !== ''), 'parent_id' => $parentId,
            'colour' => $data['colour'] ?? $category->colour ?? 'primary', 'sort_order' => $data['sort_order'] ?? $category->sort_order ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ])->save();

        return $category;
    }
}
