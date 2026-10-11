<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Inventory\Enums\UnitKind;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Models\Unit;

/**
 * Saves a unit of measure (Step 5.2). A unit is either a base unit of its kind or is given as a number of another unit of the
 * same kind (1 kg = 1000 g); a pack or container unit (box, sack) has no fixed size.
 */
class SaveUnit extends Action
{
    /**
     * @param  array{code: string, name: string, kind: string, base_unit_id?: int|null, factor?: string|null, is_active?: bool}  $data
     *
     * @throws InventoryRuleViolated
     */
    public function handle(?Unit $unit, array $data): Unit
    {
        $kind = UnitKind::from($data['kind']);
        $base = isset($data['base_unit_id']) ? Unit::query()->find($data['base_unit_id']) : null;
        $factor = (string) ($data['factor'] ?? '1');

        if ($kind === UnitKind::Pack && $base instanceof Unit) {
            throw new InventoryRuleViolated(__('A pack or container unit has no fixed size: leave out the base unit and give the size on each item.'));
        }

        if ($base instanceof Unit) {
            if ($base->kind !== $kind || $base->base_unit_id !== null || ($unit instanceof Unit && $base->id === $unit->id)) {
                throw new InventoryRuleViolated(__('Choose a base unit of the same kind (a unit that is not itself defined by another).'));
            }

            if (! BigDecimal::of($factor)->isPositive()) {
                throw new InventoryRuleViolated(__('The size must be more than zero.'));
            }
        }

        if ($unit instanceof Unit && $unit->kind !== $kind && ($unit->items()->exists() || Unit::query()->where('base_unit_id', $unit->id)->exists())) {
            throw new InventoryRuleViolated(__('This unit is in use: its kind cannot change.'));
        }

        $unit ??= new Unit;
        $unit->fill([
            'code' => mb_strtolower(trim($data['code'])), 'name' => trim($data['name']), 'kind' => $kind, 'base_unit_id' => $base?->id,
            'factor' => $base instanceof Unit ? $factor : '1', 'is_active' => $data['is_active'] ?? true,
        ])->save();

        return $unit;
    }
}
