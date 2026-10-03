<?php

namespace Modules\Guest\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Guest\Models\TravelAgent;

/**
 * @extends Factory<TravelAgent>
 */
class TravelAgentFactory extends Factory
{
    protected $model = TravelAgent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('TA-??##')),
            'name' => fake()->randomElement(['Bengal', 'Sundarban', 'Royal', 'Green', 'Blue Sky']).' '.fake()->randomElement(['Tours', 'Travels', 'Holidays']),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+8801'.fake()->numerify('#########'),
            'address' => ['line1' => fake()->streetAddress(), 'city' => 'Dhaka', 'country_code' => 'BD'],
            'commission_percent' => (string) fake()->randomElement([8, 10, 12.5, 15]),
            'credit_limit' => '50000.00',
            'is_active' => true,
        ];
    }
}
