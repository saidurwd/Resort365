<?php

namespace Modules\FrontOffice\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\FrontOffice\Database\Factories\Concerns\ResolvesProperty;
use Modules\FrontOffice\Enums\NightAuditStatus;
use Modules\FrontOffice\Enums\NightAuditTrigger;
use Modules\FrontOffice\Models\NightAudit;

/**
 * A completed audit of a past business date (dates are unique per property).
 *
 * @extends Factory<NightAudit>
 */
class NightAuditFactory extends Factory
{
    use ResolvesProperty;

    protected $model = NightAudit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now()->subDays(fake()->unique()->numberBetween(30, 3000));

        return [
            'property_id' => $this->propertyId(...),
            'business_date' => $date->toDateString(),
            'status' => NightAuditStatus::Completed,
            'trigger' => NightAuditTrigger::Scheduled,
            'started_at' => $date->copy()->addDay()->setTime(2, 0),
            'completed_at' => $date->copy()->addDay()->setTime(2, 1),
            'nights_posted' => fake()->numberBetween(0, 15),
            'no_shows' => fake()->numberBetween(0, 2),
            'holds_released' => 0,
            'steps' => [['step' => 'post_room_charges', 'result' => 'Posted 6 room nights.']],
        ];
    }
}
