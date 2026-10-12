<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesInventoryItem;
use Modules\Restaurant\Models\WastageEntry;
use Modules\Restaurant\Models\WastageLine;

/**
 * @extends Factory<WastageLine>
 */
class WastageLineFactory extends Factory
{
    use ResolvesInventoryItem;

    protected $model = WastageLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entry = WastageEntry::factory()->create();

        return ['property_id' => $entry->property_id, 'wastage_entry_id' => $entry->id, 'item_id' => $this->inventoryItemId(), 'quantity' => '1', 'unit_cost' => '100', 'total_cost' => '100'];
    }
}
