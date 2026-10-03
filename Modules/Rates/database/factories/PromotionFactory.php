<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Enums\DiscountType;
use Modules\Rates\Models\Promotion;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    use ResolvesProperty;

    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'code' => strtoupper(fake()->unique()->bothify('PROMO##??')),
            'name' => fake()->randomElement(['Monsoon offer', 'Early bird', 'Weekend escape']),
            'discount_type' => DiscountType::Percent,
            'discount_value' => '10.00',
            'is_active' => true,
        ];
    }
}
