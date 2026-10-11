<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Database\Factories\Concerns\ResolvesProperty;
use Modules\Inventory\Enums\StoreKind;
use Modules\Inventory\Models\Store;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Store::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['property_id' => $this->propertyId(), 'code' => strtoupper(fake()->unique()->lexify('S????')), 'name' => fake()->words(2, true).' store', 'kind' => StoreKind::Main, 'is_active' => true];
    }
}
