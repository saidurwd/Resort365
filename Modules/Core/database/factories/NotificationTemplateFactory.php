<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\NotificationTemplate;

/**
 * @extends Factory<NotificationTemplate>
 */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'test.'.fake()->unique()->word(),
            'channel' => 'mail',
            'locale' => 'en',
            'subject' => 'Hello {name}',
            'body' => 'Welcome to {tenant}.',
            'is_active' => true,
        ];
    }
}
