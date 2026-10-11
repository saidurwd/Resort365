<?php

namespace Modules\Inventory\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Inventory\Enums\UnitKind;
use Modules\Inventory\Exceptions\UnitNotConvertible;

/**
 * Converts quantities between units (Step 5.2). Units of one kind share a base unit: 1 of a unit = its factor in
 * base units (kg 1000, g 1). A unit of another kind needs the item's own factor (1 box = 24 pcs). Pure; quantities
 * are decimal strings with up to six decimals.
 */
class UnitConverter
{
    /**
     * @param  array{kind: UnitKind, factor: string}  $from
     * @param  array{kind: UnitKind, factor: string}  $to
     *
     * @throws UnitNotConvertible
     */
    public function between(string $quantity, array $from, array $to): string
    {
        if ($from['kind'] !== $to['kind']) {
            throw new UnitNotConvertible(__('A :from quantity cannot be turned into a :to quantity without the item\'s own conversion.', ['from' => mb_strtolower($from['kind']->label()), 'to' => mb_strtolower($to['kind']->label())]));
        }

        return $this->trim((string) BigDecimal::of($quantity)->multipliedBy($from['factor'])->dividedBy($to['factor'], 6, RoundingMode::HalfUp));
    }

    /**
     * A quantity in an item's own unit as base units: quantity × the item's factor for that unit.
     */
    public function viaItemFactor(string $quantity, string $itemFactor): string
    {
        return $this->trim((string) BigDecimal::of($quantity)->multipliedBy($itemFactor)->toScale(6, RoundingMode::HalfUp));
    }

    private function trim(string $number): string
    {
        return str_contains($number, '.') ? rtrim(rtrim($number, '0'), '.') : $number;
    }
}
