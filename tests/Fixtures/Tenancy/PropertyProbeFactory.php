<?php

namespace Tests\Fixtures\Tenancy;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Needs property_id (or a current property in PropertyContext).
 *
 * @extends Factory<PropertyProbe>
 */
class PropertyProbeFactory extends Factory
{
    protected $model = PropertyProbe::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['name' => fake()->words(2, true)];
    }
}
