<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\FolioStatus;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Models\Folio;

/**
 * @extends Factory<Folio>
 */
class FolioFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Folio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'reservation_id' => fn (array $attributes): int => $this->reservationId((int) $attributes['property_id']),
            'folio_no' => 'FOL-TEST-'.fake()->unique()->numberBetween(10000, 999999),
            'type' => FolioType::Guest,
            'bill_to_type' => BillTo::Guest,
            'name' => fake()->name(),
            'status' => FolioStatus::Open,
            'currency_code' => 'BDT',
            'balance' => '0.00',
        ];
    }
}
