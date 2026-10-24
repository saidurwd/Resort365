<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Enums\GatewayMode;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Models\PaymentGateway;

/**
 * @extends Factory<PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    use ResolvesProperty;

    protected $model = PaymentGateway::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'provider' => GatewayProvider::Sslcommerz,
            'mode' => GatewayMode::Sandbox,
            'credentials' => ['store_id' => 'testbox', 'store_password' => 'qwerty'],
            'is_enabled' => true,
        ];
    }

    public function provider(GatewayProvider $provider): static
    {
        return $this->state(['provider' => $provider, 'credentials' => match ($provider) {
            GatewayProvider::Sslcommerz => ['store_id' => 'testbox', 'store_password' => 'qwerty'],
            GatewayProvider::Bkash => ['app_key' => 'bkash-key', 'app_secret' => 'bkash-secret', 'username' => 'sandboxUser', 'password' => 'sandboxPass'],
            GatewayProvider::Stripe => ['secret_key' => 'sk_test_123', 'webhook_secret' => 'whsec_test'],
            GatewayProvider::Test => [],
        }]);
    }
}
