<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\OutletMenuItem;

/**
 * @extends Factory<OutletMenuItem>
 */
class OutletMenuItemFactory extends Factory
{
    use ResolvesProperty;

    protected $model = OutletMenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'menu_item_id' => fn (array $attributes): int => MenuItem::factory()->create(['property_id' => $attributes['property_id']])->id,
            'variant_key' => 0,
            'price' => '450.00',
            'is_available' => true,
            'is_package_eligible' => false,
        ];
    }
}
