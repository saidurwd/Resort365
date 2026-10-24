<?php

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Models\WebsiteUnit;

/**
 * @extends Factory<WebsiteUnit>
 */
class WebsiteUnitFactory extends Factory
{
    protected $model = WebsiteUnit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_site_id' => WebsiteSite::factory(),
            'property_id' => fn (array $attributes): int => (int) WebsiteSite::query()->whereKey($attributes['website_site_id'])->value('property_id'),
            'unit_key' => 'room_type:'.fake()->unique()->numberBetween(1, 99999),
            'headline' => ['en' => 'Sea-view room'],
            'description' => ['en' => fake()->sentence()],
            'is_featured' => false,
        ];
    }
}
