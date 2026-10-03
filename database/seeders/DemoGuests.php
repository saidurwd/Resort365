<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Modules\Guest\Actions\BlacklistGuest;
use Modules\Guest\Actions\SaveGuest;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Enums\VipLevel;
use Modules\Guest\Models\Company;
use Modules\Guest\Models\Guest;
use Modules\Guest\Models\TravelAgent;
use Modules\Guest\Services\IdNumberHasher;

/**
 * Guests, companies and travel agents for DemoSeeder (Step 1.2). Most guests are bulk-inserted
 * (no audit entries) so 10,000 seed quickly; a few named guests go through SaveGuest, including
 * a duplicate pair (same phone) to try the duplicate warning and merge on.
 */
final class DemoGuests
{
    private const array COMPANIES = [
        ['Meghna Group', 'BIN-000123456', '500000.00', 30], ['Padma Textiles', 'BIN-000234567', '250000.00', 15],
        ['Jamuna Pharmaceuticals', 'BIN-000345678', '300000.00', 30], ['Karnaphuli Telecom', 'BIN-000456789', '0.00', 0],
        ['Surma Holdings', 'BIN-000567890', '150000.00', 15], ['Teesta Bank', 'BIN-000678901', '1000000.00', 45],
        ['Rupsha Shipping', 'BIN-000789012', '200000.00', 30], ['Bengal Software', 'BIN-000890123', '100000.00', 15],
    ];

    private const array AGENTS = [
        ['SBT', 'Sundarban Tours', '10.00'], ['RYH', 'Royal Holidays', '12.50'], ['BST', 'Blue Sky Travels', '8.00'],
        ['GRT', 'Green Tours', '10.00'], ['BBH', 'Bay of Bengal Holidays', '15.00'],
    ];

    public static function seed(Tenant $tenant, int $guests): void
    {
        app(TenantContext::class)->run($tenant, function (Tenant $tenant) use ($guests): void {
            if (Guest::query()->exists()) {
                return;
            }

            foreach (self::COMPANIES as [$name, $bin, $limit, $terms]) {
                Company::factory()->create(['name' => $name, 'legal_name' => $name.' Ltd', 'tax_number' => $bin, 'credit_limit' => $limit, 'payment_terms_days' => $terms]);
            }

            foreach (self::AGENTS as [$code, $name, $commission]) {
                TravelAgent::factory()->create(['code' => $code, 'name' => $name, 'commission_percent' => $commission]);
            }

            self::named();
            self::bulk($tenant, $guests - 5);
        });
    }

    /**
     * Guests to try things on: a duplicate pair, a blacklisted guest and VIPs.
     */
    private static function named(): void
    {
        $company = Company::query()->where('name', 'Meghna Group')->value('id');
        $save = SaveGuest::make();

        $save->handle(null, ['title' => 'Mr', 'first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '01711-000001', 'email' => 'rahim.uddin@example.com',
            'nationality_code' => 'BD', 'id_type' => 'national_id', 'id_number' => '1990123456789', 'company_id' => $company, 'vip_level' => VipLevel::Gold->value,
            'preferences' => ['Non-smoking', 'Sea view'], 'marketing_consent' => true]);
        $save->handle(null, ['first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '+880 1711 000001', 'email' => 'rahim.u@work.example.com',
            'nationality_code' => 'BD', 'notes' => 'Booked by phone; probably the same Rahim Uddin.']);
        $save->handle(null, ['title' => 'Ms', 'first_name' => 'Ayesha', 'last_name' => 'Siddique', 'phone' => '01819-000002', 'email' => 'ayesha.siddique@example.com',
            'nationality_code' => 'BD', 'vip_level' => VipLevel::Platinum->value]);
        $save->handle(null, ['title' => 'Mr', 'first_name' => 'John', 'last_name' => 'Smith', 'phone' => '+44 20 7946 0000', 'email' => 'john.smith@example.co.uk',
            'nationality_code' => 'GB', 'id_type' => 'passport', 'id_number' => 'GB1234567']);

        $blacklisted = $save->handle(null, ['first_name' => 'Kamal', 'last_name' => 'Hossain', 'phone' => '01911-000003', 'nationality_code' => 'BD']);
        BlacklistGuest::make()->handle($blacklisted, 'Left without paying the bill (Sep 2026).', null);
    }

    /**
     * Bulk insert in batches of 500. Rows are built here rather than with the factory, which
     * checks reference data once per guest (too slow for 10,000).
     */
    private static function bulk(Tenant $tenant, int $count): void
    {
        $now = now()->toDateTimeString();
        $hasher = app(IdNumberHasher::class);
        $first = ['Rahim', 'Karim', 'Nusrat', 'Farzana', 'Tanvir', 'Sabbir', 'Ayesha', 'Imran', 'Shirin', 'Arif', 'Mitu', 'Rafiq', 'Sadia', 'Hasan', 'Jannat', 'Mahmud', 'Tania', 'Rubel', 'Nadia', 'Fahim', 'Sumaiya', 'Rakib', 'Lamia', 'Shakil'];
        $last = ['Uddin', 'Ahmed', 'Hossain', 'Rahman', 'Islam', 'Akter', 'Chowdhury', 'Khan', 'Begum', 'Sarker', 'Haque', 'Mia', 'Siddique', 'Kabir', 'Alam', 'Talukder'];

        for ($done = 0; $done < $count; $done += 500) {
            $rows = [];

            for ($i = $done; $i < min($done + 500, $count); $i++) {
                $local = $i % 10 !== 0;
                $firstName = $local ? $first[array_rand($first)] : fake()->firstName();
                $lastName = $local ? $last[array_rand($last)] : fake()->lastName();
                $idType = $local ? IdType::NationalId : IdType::Passport;
                $idNumber = $local ? (string) (1_000_000_000 + $i) : 'P'.str_pad((string) $i, 8, '0', STR_PAD_LEFT);
                $rare = fake()->numberBetween(1, 100);

                $rows[] = [
                    'tenant_id' => $tenant->id,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $i % 3 === 0 ? null : strtolower($firstName.'.'.$lastName.'.'.$i).'@example.com',
                    'phone' => '+88017'.str_pad((string) (10_000_000 + $i), 8, '0', STR_PAD_LEFT),
                    'nationality_code' => $local ? 'BD' : null,
                    'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
                    'id_type' => $idType->value,
                    'id_number' => Crypt::encryptString($idNumber),
                    'id_number_hash' => $hasher->hash($idType, $idNumber),
                    'address' => json_encode(['city' => $local ? fake()->randomElement(['Dhaka', 'Chattogram', 'Sylhet', 'Khulna']) : fake()->city()]),
                    'vip_level' => $rare <= 2 ? VipLevel::Silver->value : ($rare === 3 ? VipLevel::Gold->value : VipLevel::None->value),
                    'preferences' => json_encode($rare > 70 ? [fake()->randomElement(['Non-smoking', 'High floor', 'Quiet room', 'Extra pillows', 'Vegetarian'])] : []),
                    'marketing_consent' => $rare % 2 === 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('guests')->insert($rows);
        }
    }
}
