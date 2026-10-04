<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\OutletType;
use Modules\Restaurant\Models\Outlet;

/**
 * @extends Factory<Outlet>
 */
class OutletFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Outlet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'code' => 'O'.fake()->unique()->numberBetween(100, 99999),
            'name' => fake()->randomElement(['Main Restaurant', 'Pool Bar', 'Beach Grill', 'Café']),
            'type' => OutletType::Restaurant,
            'prices_include_tax' => false,
            'bill_prefix' => 'MR',
            'is_active' => true,
        ];
    }
}
