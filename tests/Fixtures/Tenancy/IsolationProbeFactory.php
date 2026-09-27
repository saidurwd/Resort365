<?php

namespace Tests\Fixtures\Tenancy;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IsolationProbe>
 */
class IsolationProbeFactory extends Factory
{
    protected $model = IsolationProbe::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('P-####')),
            'name' => fake()->words(2, true),
        ];
    }
}
