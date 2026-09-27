<?php

namespace Modules\IAM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\IAM\Enums\LoginEvent;
use Modules\IAM\Models\LoginHistory;

/**
 * @extends Factory<LoginHistory>
 */
class LoginHistoryFactory extends Factory
{
    protected $model = LoginHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => LoginEvent::Login,
            'email' => fake()->safeEmail(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
