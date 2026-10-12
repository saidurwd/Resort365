<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Database\Factories\Concerns\ResolvesReferences;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorItem;

/**
 * @extends Factory<VendorItem>
 */
class VendorItemFactory extends Factory
{
    use ResolvesReferences;

    protected $model = VendorItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['vendor_id' => Vendor::factory(), 'item_id' => $this->itemId(), 'price' => '120.0000', 'min_quantity' => '0'];
    }
}
