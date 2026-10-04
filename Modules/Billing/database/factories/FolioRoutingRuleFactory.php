<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioRoutingRule;

/**
 * @extends Factory<FolioRoutingRule>
 */
class FolioRoutingRuleFactory extends Factory
{
    use ResolvesProperty;

    protected $model = FolioRoutingRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'reservation_id' => fn (array $attributes): int => $this->reservationId((int) $attributes['property_id'], new: true),
            'category' => fake()->randomElement(ChargeCategory::cases()),
            'target_folio_id' => fn (array $attributes): int => Folio::factory()->create(['property_id' => $attributes['property_id'], 'reservation_id' => $attributes['reservation_id']])->id,
        ];
    }
}
