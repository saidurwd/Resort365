<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\ComboComponent;
use Modules\Restaurant\Models\MenuItem;

/**
 * @extends Factory<ComboComponent>
 */
class ComboComponentFactory extends Factory
{
    use ResolvesProperty;

    protected $model = ComboComponent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'menu_item_id' => fn (array $attributes): int => MenuItem::factory()->create(['property_id' => $attributes['property_id'], 'kind' => 'combo'])->id,
            'component_item_id' => fn (array $attributes): int => MenuItem::factory()->create(['property_id' => $attributes['property_id']])->id,
            'quantity' => 1,
        ];
    }
}
