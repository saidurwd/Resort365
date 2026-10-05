<?php

namespace Modules\Restaurant\Services;

/**
 * Checks the modifiers chosen for an item against its modifier groups (ARCHITECTURE §5.10.2), without
 * the database: every option must be an active option of one of the item's groups; each group needs
 * between its min and max choices (min > 0 = required). Returns the chosen options in group order.
 *
 * A group is array{id: int, name: string, min: int, max: int, options: array<int, array{name: string, price: string, active: bool}>}.
 */
class ModifierRules
{
    /**
     * @param  list<array{id: int, name: string, min: int, max: int, options: array<int, array{name: string, price: string, active: bool}>}>  $groups
     * @param  list<int>  $chosen  modifier ids
     * @return array{selected: list<array{id: int, group: string, name: string, price: string}>, errors: list<string>}
     */
    public function check(array $groups, array $chosen): array
    {
        $chosen = array_values(array_unique($chosen));
        $known = [];
        $selected = [];
        $errors = [];

        foreach ($groups as $group) {
            $picked = array_values(array_filter($chosen, fn (int $id): bool => isset($group['options'][$id]) && $group['options'][$id]['active']));
            $known = [...$known, ...$picked];

            if (count($picked) < $group['min']) {
                $errors[] = $group['min'] === 1 ? __('Choose a :group.', ['group' => mb_strtolower($group['name'])]) : __('Choose at least :count for :group.', ['count' => $group['min'], 'group' => $group['name']]);
            }

            if (count($picked) > $group['max']) {
                $errors[] = __('Choose at most :count for :group.', ['count' => $group['max'], 'group' => $group['name']]);
            }

            foreach ($picked as $id) {
                $selected[] = ['id' => $id, 'group' => $group['name'], 'name' => $group['options'][$id]['name'], 'price' => $group['options'][$id]['price']];
            }
        }

        if (array_diff($chosen, $known) !== []) {
            $errors[] = __('Some choices do not belong to this item.');
        }

        return ['selected' => $errors === [] ? $selected : [], 'errors' => $errors];
    }
}
