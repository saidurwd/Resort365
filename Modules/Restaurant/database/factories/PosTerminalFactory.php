<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Models\PosTerminal;

/**
 * @extends Factory<PosTerminal>
 */
class PosTerminalFactory extends Factory
{
    use ResolvesProperty;

    protected $model = PosTerminal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'name' => 'Tablet '.fake()->unique()->numberBetween(1, 99999),
            'device_token' => fn (): string => hash('sha256', Str::random(40)),
            'is_active' => true,
        ];
    }
}
