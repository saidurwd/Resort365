<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Enums\VendorStatus;
use Modules\Procurement\Models\Vendor;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => 'V'.fake()->unique()->numerify('#####'), 'name' => fake()->company(), 'email' => fake()->safeEmail(), 'phone' => fake()->numerify('01#########'), 'currency' => 'BDT', 'payment_terms_days' => 30, 'status' => VendorStatus::Active];
    }
}
