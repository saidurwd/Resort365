<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Models\ItemCategory;

/**
 * Saves an item category (Step 5.2); it cannot be placed inside itself or one of its own subcategories.
 */
class SaveCategory extends Action
{
    /**
     * @param  array{name: string, parent_id?: int|null, account_class?: string|null, sort_order?: int, is_active?: bool}  $data
     *
     * @throws InventoryRuleViolated
     */
    public function handle(?ItemCategory $category, array $data): ItemCategory
    {
        $parentId = $data['parent_id'] ?? null;

        if ($category instanceof ItemCategory && $parentId !== null) {
            for ($id = $parentId, $guard = 0; $id !== null && $guard < 20; $guard++) {
                if ((int) $id === $category->id) {
                    throw new InventoryRuleViolated(__('A category cannot be inside itself.'));
                }

                $id = ItemCategory::query()->whereKey($id)->value('parent_id');
            }
        }

        $category ??= new ItemCategory;
        $category->fill(['name' => trim($data['name']), 'parent_id' => $parentId, 'account_class' => $data['account_class'] ?? null, 'sort_order' => $data['sort_order'] ?? $category->sort_order ?? 0, 'is_active' => $data['is_active'] ?? true])->save();

        return $category;
    }
}
