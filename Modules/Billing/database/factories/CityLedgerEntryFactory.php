<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Database\Factories\Concerns\ResolvesProperty;
use Modules\Billing\Enums\CityLedgerStatus;
use Modules\Billing\Models\CityLedgerEntry;

/**
 * @extends Factory<CityLedgerEntry>
 */
class CityLedgerEntryFactory extends Factory
{
    use ResolvesProperty;

    protected $model = CityLedgerEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'company_id' => fn (array $attributes): int => $this->companyId((int) $attributes['property_id']),
            'posted_on' => now()->toDateString(),
            'due_on' => now()->addDays(30)->toDateString(),
            'description' => 'Folio balance on account',
            'amount' => '15180.00',
            'status' => CityLedgerStatus::Open,
        ];
    }
}
