<?php

namespace Database\Factories;

use App\Models\DatabaseNotification;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DatabaseNotification>
 */
class DatabaseNotificationFactory extends Factory
{
    protected $model = DatabaseNotification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => 'test.notification',
            'notifiable_type' => 'user',
            'notifiable_id' => fake()->numberBetween(1, 1_000_000),
            'data' => ['title' => fake()->sentence(3)],
        ];
    }
}
