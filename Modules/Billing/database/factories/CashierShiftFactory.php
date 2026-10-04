<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Enums\CashierShiftStatus;
use Modules\Billing\Models\CashierShift;

/**
 * A closed, balanced shift by default (open ones are unique per cashier); open() for a shift in
 * progress.
 *
 * @extends Factory<CashierShift>
 */
class CashierShiftFactory extends Factory
{
    use ResolvesProperty;

    protected $model = CashierShift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'user_id' => fn (array $attributes): int => $this->cashierId((int) $attributes['property_id']),
            'business_date' => now()->toDateString(),
            'opened_at' => now()->subHours(8),
            'opening_float' => '5000.00',
            'closed_at' => now(),
            'cash_received' => '12000.00',
            'cash_refunded' => '0.00',
            'expected_cash' => '17000.00',
            'counted_cash' => '17000.00',
            'cash_variance' => '0.00',
            'denominations' => ['1000' => 17],
            'status' => CashierShiftStatus::Closed,
            'open_user_id' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'closed_at' => null, 'cash_received' => null, 'cash_refunded' => null, 'expected_cash' => null, 'counted_cash' => null,
            'cash_variance' => null, 'denominations' => null, 'status' => CashierShiftStatus::Open,
        ])->afterMaking(function (CashierShift $shift): void {
            $shift->open_user_id = $shift->user_id;
        });
    }

    /**
     * A user of the property's tenant (a new one, so open shifts never clash).
     */
    private function cashierId(int $propertyId): int
    {
        $tenantId = (int) DB::table('properties')->where('id', $propertyId)->value('tenant_id');

        return DB::table('users')->insertGetId([
            'tenant_id' => $tenantId, 'name' => fake()->name(), 'email' => fake()->unique()->safeEmail(), 'password' => bcrypt('password'),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
