<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Database\Factories\Concerns\ResolvesRoom;
use Modules\Reservation\Enums\QuoteStatus;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Models\Quote;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    use ResolvesRoom;

    protected $model = Quote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+2 weeks', '+6 months');

        return [
            'property_id' => $this->propertyId(...),
            'code' => 'QUO-TEST-'.fake()->unique()->numberBetween(10000, 999999),
            'status' => QuoteStatus::Draft,
            'source' => ReservationSource::Email,
            'guest_id' => fn (array $attributes): int => $this->guestId((int) $attributes['property_id']),
            'rate_plan_id' => fn (array $attributes): int => $this->ratePlanId((int) $attributes['property_id']),
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => (clone $checkIn)->modify('+2 days')->format('Y-m-d'),
            'adults' => 2,
            'children' => 0,
            'currency_code' => 'BDT',
            'subtotal' => '12000.00',
            'tax_total' => '3180.00',
            'grand_total' => '15180.00',
            'deposit_percent' => '30.00',
            'deposit_amount' => '4554.00',
            'valid_until' => now()->addWeek()->toDateString(),
        ];
    }
}
