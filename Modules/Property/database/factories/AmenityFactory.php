<?php

namespace Modules\Property\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Property\Enums\AmenityCategory;
use Modules\Property\Models\Amenity;

/**
 * @extends Factory<Amenity>
 */
class AmenityFactory extends Factory
{
    protected $model = Amenity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$name, $icon, $category] = fake()->randomElement([
            ['Air conditioning', 'bi-snow', AmenityCategory::InRoom], ['Wi-Fi', 'bi-wifi', AmenityCategory::InRoom],
            ['Hot water', 'bi-droplet', AmenityCategory::Bathroom], ['Balcony', 'bi-tree', AmenityCategory::Outdoor],
            ['Room service', 'bi-bell', AmenityCategory::Service],
        ]);

        return [
            'name' => $name.' '.fake()->unique()->numberBetween(1, 99999),
            'icon' => $icon,
            'category' => $category,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
