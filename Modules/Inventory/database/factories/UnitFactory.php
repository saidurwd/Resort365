<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Enums\UnitKind;
use Modules\Inventory\Models\Unit;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => strtolower(fake()->unique()->lexify('u????')), 'name' => fake()->word(), 'kind' => UnitKind::Count, 'factor' => '1', 'is_active' => true];
    }
}
