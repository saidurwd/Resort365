<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Enums\UnitKind;
use Modules\Inventory\Models\Unit;

/**
 * The units every resort needs (Step 5.2): pieces, weights, volumes, lengths and the usual containers. Units the tenant
 * already has (by code) are left alone.
 */
class SeedStandardUnits extends Action
{
    /**
     * @var list<array{0: string, 1: string, 2: UnitKind, 3: string|null, 4: string}>
     */
    public const array UNITS = [
        ['pcs', 'Piece', UnitKind::Count, null, '1'], ['dozen', 'Dozen', UnitKind::Count, 'pcs', '12'], ['pair', 'Pair', UnitKind::Count, 'pcs', '2'],
        ['g', 'Gram', UnitKind::Weight, null, '1'], ['kg', 'Kilogram', UnitKind::Weight, 'g', '1000'], ['lb', 'Pound', UnitKind::Weight, 'g', '453.592'],
        ['ml', 'Millilitre', UnitKind::Volume, null, '1'], ['l', 'Litre', UnitKind::Volume, 'ml', '1000'],
        ['m', 'Metre', UnitKind::Length, null, '1'], ['cm', 'Centimetre', UnitKind::Length, 'm', '0.01'],
        ['box', 'Box', UnitKind::Pack, null, '1'], ['carton', 'Carton', UnitKind::Pack, null, '1'], ['pack', 'Pack', UnitKind::Pack, null, '1'], ['bottle', 'Bottle', UnitKind::Pack, null, '1'],
        ['can', 'Can', UnitKind::Pack, null, '1'], ['sack', 'Sack', UnitKind::Pack, null, '1'], ['roll', 'Roll', UnitKind::Pack, null, '1'], ['bag', 'Bag', UnitKind::Pack, null, '1'],
        ['tray', 'Tray', UnitKind::Pack, null, '1'], ['jar', 'Jar', UnitKind::Pack, null, '1'], ['tin', 'Tin', UnitKind::Pack, null, '1'], ['crate', 'Crate', UnitKind::Pack, null, '1'],
    ];

    /**
     * @return int the number of units created
     */
    public function handle(): int
    {
        $created = 0;

        foreach (self::UNITS as [$code, $name, $kind, $base, $factor]) {
            if (Unit::query()->where('code', $code)->exists()) {
                continue;
            }

            Unit::query()->create(['code' => $code, 'name' => $name, 'kind' => $kind, 'base_unit_id' => $base !== null ? Unit::query()->where('code', $base)->value('id') : null, 'factor' => $factor, 'is_active' => true]);
            $created++;
        }

        return $created;
    }
}
