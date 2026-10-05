<?php

namespace Modules\Restaurant\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Restaurant\Database\Factories\Concerns\ResolvesProperty;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;

/**
 * @extends Factory<PosOrderLine>
 */
class PosOrderLineFactory extends Factory
{
    use ResolvesProperty;

    protected $model = PosOrderLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'pos_order_id' => fn (array $attributes): int => PosOrder::factory()->create(['property_id' => $attributes['property_id']])->id,
            'menu_item_id' => fn (array $attributes): int => MenuItem::factory()->create(['property_id' => $attributes['property_id']])->id,
            'name_snapshot' => 'Chicken curry',
            'quantity' => 1,
            'unit_price' => '450.00',
            'modifier_total' => '0.00',
            'line_total' => '450.00',
            'course' => Course::Main,
            'status' => OrderLineStatus::Pending,
        ];
    }
}
