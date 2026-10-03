<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\Payment;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = number_format(fake()->numberBetween(20, 400) * 100, 2, '.', '');
        $method = fake()->randomElement(PaymentMethod::cases());

        return [
            'property_id' => $this->propertyId(...),
            'receipt_no' => 'PAY-TEST-'.fake()->unique()->numberBetween(10000, 999999),
            'payment_type' => PaymentType::Deposit,
            'method' => $method,
            'amount' => $amount,
            'currency_code' => 'BDT',
            'exchange_rate' => '1',
            'base_amount' => $amount,
            'reference' => $method->needsReference() ? strtoupper(fake()->bothify('TXN########')) : null,
            'status' => PaymentStatus::Succeeded,
            'received_at' => now(),
        ];
    }

    public function forReservation(int $reservationId): static
    {
        return $this->state(['reservation_id' => $reservationId]);
    }
}
