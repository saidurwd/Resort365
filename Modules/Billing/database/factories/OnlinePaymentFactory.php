<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Enums\OnlinePaymentPurpose;
use Modules\Billing\Enums\OnlinePaymentStatus;
use Modules\Billing\Models\OnlinePayment;
use Modules\Billing\Models\PaymentGateway;

/**
 * @extends Factory<OnlinePayment>
 */
class OnlinePaymentFactory extends Factory
{
    protected $model = OnlinePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_gateway_id' => PaymentGateway::factory(),
            'property_id' => fn (array $attributes): int => (int) PaymentGateway::query()->whereKey($attributes['payment_gateway_id'])->value('property_id'),
            'uuid' => (string) Str::uuid(),
            'provider' => GatewayProvider::Sslcommerz,
            'reservation_id' => fake()->numberBetween(1, 9_999_999),
            'purpose' => OnlinePaymentPurpose::Deposit,
            'amount' => '4554.00',
            'currency_code' => 'BDT',
            'status' => OnlinePaymentStatus::Pending,
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
