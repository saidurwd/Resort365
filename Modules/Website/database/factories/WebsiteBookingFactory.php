<?php

namespace Modules\Website\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Website\Enums\PayChoice;
use Modules\Website\Models\WebsiteBooking;
use Modules\Website\Models\WebsiteSite;

/**
 * @extends Factory<WebsiteBooking>
 */
class WebsiteBookingFactory extends Factory
{
    protected $model = WebsiteBooking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_site_id' => WebsiteSite::factory(),
            'property_id' => fn (array $attributes): int => (int) WebsiteSite::query()->whereKey($attributes['website_site_id'])->value('property_id'),
            'reservation_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'reservation_code' => fn (array $attributes): string => 'RSV-W'.$attributes['reservation_id'],
            'pay_choice' => PayChoice::Deposit,
            'amount_due_now' => '4554.00',
            'currency_code' => 'BDT',
            'email' => fake()->safeEmail(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
