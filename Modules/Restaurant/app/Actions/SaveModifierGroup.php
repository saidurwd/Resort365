<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\Modifier;
use Modules\Restaurant\Models\ModifierGroup;

/**
 * Creates or changes a modifier group and its options in one transaction. The group asks for between
 * min and max choices (min > 0 = required); options are matched by id, new ones added, missing ones
 * removed.
 */
class SaveModifierGroup extends Action
{
    /**
     * @param  list<array{id?: int|null, name: string, price_delta?: string|null, is_active?: bool}>  $modifiers
     *
     * @throws RestaurantSetupInvalid
     */
    public function handle(int $propertyId, ?ModifierGroup $group, string $name, int $minSelect, int $maxSelect, array $modifiers): ModifierGroup
    {
        if ($maxSelect < max(1, $minSelect)) {
            throw new RestaurantSetupInvalid(__('The most choices must be at least the fewest (and at least 1).'));
        }

        if (count($modifiers) < $minSelect || $modifiers === []) {
            throw new RestaurantSetupInvalid(__('Add at least :count options.', ['count' => max(1, $minSelect)]));
        }

        return $this->transaction(function () use ($propertyId, $group, $name, $minSelect, $maxSelect, $modifiers): ModifierGroup {
            $group ??= new ModifierGroup(['property_id' => $propertyId]);
            $group->fill(['name' => $name, 'min_select' => $minSelect, 'max_select' => $maxSelect])->save();
            $keep = [];

            foreach ($modifiers as $order => $data) {
                $modifier = isset($data['id']) ? Modifier::query()->where('modifier_group_id', $group->id)->find($data['id']) : null;
                $modifier ??= new Modifier(['property_id' => $group->property_id, 'modifier_group_id' => $group->id]);
                $modifier->fill([
                    'name' => trim($data['name']), 'price_delta' => (string) BigDecimal::of($data['price_delta'] ?? '0' ?: '0')->toScale(2),
                    'sort_order' => $order, 'is_active' => $data['is_active'] ?? true,
                ])->save();
                $keep[] = $modifier->id;
            }

            Modifier::query()->where('modifier_group_id', $group->id)->whereNotIn('id', $keep)->delete();

            return $group;
        });
    }
}
