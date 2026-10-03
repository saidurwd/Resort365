<?php

namespace Modules\Rates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Rates\Database\Factories\Concerns\ResolvesProperty;
use Modules\Rates\Enums\BookingChannel;
use Modules\Rates\Enums\MealPlan;
use Modules\Rates\Models\RatePlan;

/**
 * @extends Factory<RatePlan>
 */
class RatePlanFactory extends Factory
{
    use ResolvesProperty;

    protected $model = RatePlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$code, $name, $meal, $adult, $child] = fake()->randomElement([
            ['RO', 'Room Only', MealPlan::EP, '0.00', '0.00'], ['BB', 'Bed & Breakfast', MealPlan::CP, '800.00', '400.00'],
            ['HB', 'Half Board', MealPlan::MAP, '2000.00', '1000.00'],
        ]);

        return [
            'property_id' => $this->propertyId(...),
            'code' => $code.fake()->unique()->numberBetween(1, 99999),
            'name' => $name,
            'meal_plan' => $meal,
            'meal_adult_amount' => $adult,
            'meal_child_amount' => $child,
            'is_refundable' => true,
            'prices_include_tax' => false,
            'tax_category_id' => null,
            'channels' => [BookingChannel::FrontDesk->value, BookingChannel::Online->value],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
