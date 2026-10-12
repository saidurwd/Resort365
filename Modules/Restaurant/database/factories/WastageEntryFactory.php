<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesInventoryItem;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\WastageReason;
use Modules\Restaurant\Models\WastageEntry;

/**
 * @extends Factory<WastageEntry>
 */
class WastageEntryFactory extends Factory
{
    use ResolvesInventoryItem;
    use ResolvesProperty;

    protected $model = WastageEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $propertyId = $this->propertyId();

        return ['property_id' => $propertyId, 'wastage_no' => 'WST-'.fake()->unique()->numerify('####-#####'), 'store_id' => $this->inventoryStoreId($propertyId), 'wasted_on' => now()->toDateString(), 'reason' => WastageReason::Spoiled, 'recorded_by' => 1, 'total_cost' => '100'];
    }
}
