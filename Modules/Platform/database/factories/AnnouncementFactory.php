<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\Enums\AnnouncementAudience;
use Modules\Platform\Enums\AnnouncementLevel;
use Modules\Platform\Models\Announcement;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['title' => fake()->sentence(4), 'body' => fake()->sentence(12), 'level' => AnnouncementLevel::Info, 'audience' => AnnouncementAudience::All, 'starts_at' => now()->subMinute(), 'ends_at' => null];
    }
}
