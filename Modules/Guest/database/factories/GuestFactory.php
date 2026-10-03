<?php

namespace Modules\Guest\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Enums\VipLevel;
use Modules\Guest\Models\Guest;
use Modules\Guest\Services\IdNumberHasher;

/**
 * Guests of the current tenant: mostly Bangladeshi, some international. Phone numbers are in
 * international format and the ID hash matches the encrypted number, as SaveGuest stores them.
 *
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    protected $model = Guest::class;

    private const array FIRST_NAMES = ['Rahim', 'Karim', 'Nusrat', 'Farzana', 'Tanvir', 'Sabbir', 'Ayesha', 'Imran', 'Shirin', 'Arif', 'Mitu', 'Rafiq', 'Sadia', 'Hasan', 'Jannat', 'Mahmud', 'Tania', 'Rubel', 'Nadia', 'Fahim'];

    private const array LAST_NAMES = ['Uddin', 'Ahmed', 'Hossain', 'Rahman', 'Islam', 'Akter', 'Chowdhury', 'Khan', 'Begum', 'Sarker', 'Haque', 'Mia', 'Siddique', 'Kabir', 'Alam'];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        DB::table('countries')->insertOrIgnore(['code' => 'BD', 'iso3' => 'BGD', 'numeric_code' => '050', 'name' => 'Bangladesh']);

        $local = fake()->boolean(85);
        $first = $local ? fake()->randomElement(self::FIRST_NAMES) : fake()->firstName();
        $last = $local ? fake()->randomElement(self::LAST_NAMES) : fake()->lastName();
        $idType = $local ? IdType::NationalId : IdType::Passport;
        $idNumber = $local ? fake()->numerify('##########') : strtoupper(fake()->bothify('??#######'));

        return [
            'title' => fake()->randomElement(['Mr', 'Mrs', 'Ms', null]),
            'first_name' => $first,
            'last_name' => $last,
            'email' => fake()->boolean(70) ? strtolower($first.'.'.$last.fake()->unique()->numberBetween(1, 999999)).'@example.com' : null,
            'phone' => '+8801'.fake()->randomElement(['3', '5', '6', '7', '8', '9']).fake()->unique()->numerify('########'),
            'nationality_code' => $local ? 'BD' : null,
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'id_type' => $idType,
            'id_number' => $idNumber,
            'id_number_hash' => app(IdNumberHasher::class)->hash($idType, $idNumber),
            'address' => ['line1' => fake()->streetAddress(), 'city' => $local ? fake()->randomElement(['Dhaka', 'Chattogram', 'Sylhet', 'Khulna']) : fake()->city(), 'country_code' => $local ? 'BD' : null],
            'vip_level' => VipLevel::None,
            'preferences' => fake()->boolean(30) ? [fake()->randomElement(['Non-smoking', 'High floor', 'Quiet room', 'Extra pillows', 'Vegetarian'])] : [],
            'marketing_consent' => fake()->boolean(40),
        ];
    }

    public function blacklisted(string $reason = 'Damaged property during last stay'): static
    {
        return $this->state(['is_blacklisted' => true, 'blacklist_reason' => $reason, 'blacklisted_at' => now()]);
    }
}
