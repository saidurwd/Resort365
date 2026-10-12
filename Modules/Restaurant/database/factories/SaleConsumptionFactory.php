<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesInventoryItem;
use Modules\Restaurant\Models\PosBillLine;
use Modules\Restaurant\Models\SaleConsumption;

/**
 * @extends Factory<SaleConsumption>
 */
class SaleConsumptionFactory extends Factory
{
    use ResolvesInventoryItem;

    protected $model = SaleConsumption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $line = PosBillLine::factory()->create();
        $bill = $line->bill()->firstOrFail();

        return [
            'property_id' => $line->property_id, 'outlet_id' => $bill->outlet_id, 'pos_bill_id' => $bill->id, 'pos_bill_line_id' => $line->id, 'item_id' => $this->inventoryItemId(),
            'store_id' => $this->inventoryStoreId($line->property_id), 'quantity' => '0.2000', 'unit_cost' => '100', 'total_cost' => '20', 'is_complimentary' => false, 'business_date' => $bill->business_date,
        ];
    }
}
