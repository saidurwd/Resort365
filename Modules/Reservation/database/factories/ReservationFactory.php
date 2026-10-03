<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Database\Factories\Concerns\ResolvesRoom;
use Modules\Reservation\Enums\PaymentStatus;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;

/**
 * Bare reservations (no items or locks) for tests; real ones come from CreateReservation.
 *
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    use ResolvesRoom;

    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 week', '+1 year');

        return [
            'property_id' => $this->propertyId(...),
            'code' => 'RSV-TEST-'.fake()->unique()->numberBetween(10000, 999999),
            'status' => ReservationStatus::Tentative,
            'payment_status' => PaymentStatus::Unpaid,
            'source' => ReservationSource::FrontDesk,
            'primary_guest_id' => fn (array $attributes): int => $this->guestId((int) $attributes['property_id']),
            'rate_plan_id' => fn (array $attributes): int => $this->ratePlanId((int) $attributes['property_id']),
            'check_in' => $checkIn->format('Y-m-d'),
            'check_out' => (clone $checkIn)->modify('+2 days')->format('Y-m-d'),
            'adults' => 2,
            'children' => 0,
            'currency_code' => 'BDT',
            'subtotal' => '12000.00',
            'grand_total' => '15180.00',
            'deposit_percent' => '30.00',
            'deposit_required' => '4554.00',
            'balance_due' => '15180.00',
        ];
    }
}
