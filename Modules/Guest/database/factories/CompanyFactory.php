<?php

namespace Modules\Guest\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Guest\Models\Company;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['Meghna', 'Padma', 'Jamuna', 'Karnaphuli', 'Surma', 'Teesta', 'Rupsha']).' '
            .fake()->randomElement(['Group', 'Textiles', 'Pharmaceuticals', 'Telecom', 'Holdings', 'Bank']);

        return [
            'name' => $name.' '.fake()->unique()->numberBetween(1, 99999),
            'legal_name' => $name.' Ltd',
            'tax_number' => fake()->numerify('BIN-#########'),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '+8802'.fake()->numerify('#########'),
            'address' => ['line1' => fake()->streetAddress(), 'city' => 'Dhaka', 'country_code' => 'BD'],
            'credit_limit' => fake()->randomElement([0, 100000, 250000, 500000]).'.00',
            'payment_terms_days' => fake()->randomElement([0, 15, 30]),
            'is_active' => true,
        ];
    }
}
