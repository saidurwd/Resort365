<?php

namespace Modules\IAM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\IAM\Models\ApiToken;
use Modules\IAM\Models\User;

/**
 * @extends Factory<ApiToken>
 */
class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tokenable_type' => (new User)->getMorphClass(),
            'tokenable_id' => User::factory(),
            'name' => fake()->randomElement(['Booking website', 'Channel manager', 'Reporting script']),
            'token' => hash('sha256', Str::random(40)),
            'abilities' => ['reservation.availability.view'],
        ];
    }
}
