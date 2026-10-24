<?php

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Website\Database\Factories\Concerns\ResolvesProperty;
use Modules\Website\Enums\SiteTheme;
use Modules\Website\Models\WebsiteSite;

/**
 * @extends Factory<WebsiteSite>
 */
class WebsiteSiteFactory extends Factory
{
    use ResolvesProperty;

    protected $model = WebsiteSite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->city().' Resort';

        return [
            'property_id' => $this->propertyId(...),
            'slug' => fake()->unique()->slug(2),
            'is_published' => true,
            'published_at' => now(),
            'theme' => SiteTheme::Forest,
            'name' => $name,
            'tagline' => ['en' => 'A quiet place by the sea'],
            'intro_title' => ['en' => 'Welcome to '.$name],
            'intro_body' => ['en' => fake()->paragraph()],
            'highlights' => ['en' => "Private beach\nOrganic garden\nSea-view terrace"],
            'phone' => '+880 1711 000000',
            'email' => fake()->safeEmail(),
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false, 'published_at' => null]);
    }
}
