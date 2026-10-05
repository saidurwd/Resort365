<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\PosSessionStatus;
use Modules\Restaurant\Models\PosSession;

/**
 * A closed, balanced session of yesterday (open ones are unique per terminal).
 *
 * @extends Factory<PosSession>
 */
class PosSessionFactory extends Factory
{
    use ResolvesProperty;

    protected $model = PosSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'outlet_id' => fn (array $attributes): int => $this->outletId((int) $attributes['property_id']),
            'pos_terminal_id' => fn (array $attributes): int => $this->terminalId((int) $attributes['outlet_id']),
            'business_date' => now()->subDay()->toDateString(),
            'opened_by' => fn (array $attributes): int => $this->userId((int) $attributes['property_id']),
            'opened_at' => now()->subDay()->setTime(7, 0),
            'opening_float' => '3000.00',
            'closed_at' => now()->subDay()->setTime(23, 0),
            'cash_received' => '0.00',
            'cash_refunded' => '0.00',
            'expected_cash' => '3000.00',
            'counted_cash' => '3000.00',
            'cash_variance' => '0.00',
            'denominations' => ['1000' => 3],
            'status' => PosSessionStatus::Closed,
        ];
    }
}
