<?php

namespace Modules\Property\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Property\Enums\PropertyStatus;
use Modules\Property\Models\Property;

/**
 * Properties of the current tenant (run inside TenantContext::run()). Ensures the reference
 * rows it points at exist, so tests need no seeder.
 *
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Reference rows (Core's central tables) the defaults point at.
        DB::table('countries')->insertOrIgnore(['code' => 'BD', 'iso3' => 'BGD', 'numeric_code' => '050', 'name' => 'Bangladesh']);
        DB::table('currencies')->insertOrIgnore(['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => 'BDT', 'decimals' => 2]);
        DB::table('timezones')->insertOrIgnore(['name' => 'Asia/Dhaka', 'country_code' => 'BD', 'utc_offset' => '+06:00']);

        $name = fake()->unique()->city().' Resort';

        return [
            'code' => strtoupper(Str::substr(Str::slug($name, ''), 0, 3)).fake()->unique()->numberBetween(10, 999),
            'name' => $name,
            'email' => fake()->safeEmail(),
            'phone' => '+8801'.fake()->numerify('#########'),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country_code' => 'BD',
            'timezone' => 'Asia/Dhaka',
            'currency_code' => 'BDT',
            'check_in_time' => '14:00',
            'check_out_time' => '12:00',
            'business_date' => now('Asia/Dhaka')->toDateString(),
            'status' => PropertyStatus::Active,
        ];
    }
}
