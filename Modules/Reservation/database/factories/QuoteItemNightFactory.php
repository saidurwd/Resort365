<?php

namespace Modules\Reservation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reservation\Database\Factories\Concerns\ResolvesRoom;
use Modules\Reservation\Models\QuoteItem;
use Modules\Reservation\Models\QuoteItemNight;

/**
 * @extends Factory<QuoteItemNight>
 */
class QuoteItemNightFactory extends Factory
{
    use ResolvesRoom;

    protected $model = QuoteItemNight::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'quote_item_id' => fn (array $attributes): int => QuoteItem::factory()->create(['property_id' => $attributes['property_id']])->id,
            'stay_date' => fake()->unique()->dateTimeBetween('+2 weeks', '+3 years')->format('Y-m-d'),
            'base_rate' => '6000.00',
            'net_amount' => '6000.00',
            'tax_amount' => '1590.00',
            'total_amount' => '7590.00',
            'rate_source' => 'base',
        ];
    }
}
