<?php

namespace Modules\Procurement\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorContact;

/**
 * @extends Factory<VendorContact>
 */
class VendorContactFactory extends Factory
{
    protected $model = VendorContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['vendor_id' => Vendor::factory(), 'name' => fake()->name(), 'role' => 'Sales', 'email' => fake()->safeEmail(), 'phone' => fake()->numerify('01#########'), 'is_primary' => false];
    }
}
