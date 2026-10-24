<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Models\GatewayEvent;

/**
 * @extends Factory<GatewayEvent>
 */
class GatewayEventFactory extends Factory
{
    use ResolvesProperty;

    protected $model = GatewayEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'provider' => GatewayProvider::Sslcommerz,
            'kind' => 'notification',
            'event_id' => 'ipn:'.fake()->unique()->uuid(),
            'signature_valid' => true,
            'outcome' => 'paid',
            'payload' => ['status' => 'VALID'],
        ];
    }
}
