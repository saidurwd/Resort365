<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Database\Seeders\ReferenceDataSeeder;
use Modules\Core\Models\ExchangeRate;
use Modules\Core\Notifications\WelcomeNotification;
use Modules\IAM\Actions\SeedDefaultRoles;
use Modules\IAM\Actions\SyncPermissions;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Property\Actions\CreateCottageWithRooms;
use Modules\Property\Actions\SaveAmenity;
use Modules\Property\Actions\SaveCottageType;
use Modules\Property\Actions\SaveDepartment;
use Modules\Property\Actions\SaveProperty;
use Modules\Property\Actions\SaveRoom;
use Modules\Property\Actions\SaveRoomType;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Department;
use Modules\Property\Models\Property;

/**
 * Demo data for trying each plan step (DEVELOPMENT_PLAN §2, "Demo data used throughout").
 * Steps extend this seeder; tenant-owned data is created inside TenantContext::run().
 */
class DemoSeeder extends Seeder
{
    /**
     * Demo password for every seeded account (demo only; real passwords follow the password policy).
     */
    public const string PASSWORD = 'password';

    /**
     * @var array<string, string> tenant slug => email domain (others use {slug}.test)
     */
    private const array DOMAINS = ['rodela' => 'rodelaresort.com'];

    public function run(): void
    {
        // A fresh demo database reuses tenant ids, so drop cached settings and permissions
        // (the cache is a separate Redis database from sessions).
        Cache::flush();

        // Demo bookings and payments send no guest emails: queued jobs are discarded while seeding.
        config(['queue.connections.demo-discard' => ['driver' => 'null'], 'queue.default' => 'demo-discard']);

        $this->call(ReferenceDataSeeder::class);

        $rodela = $this->tenant('rodela', 'Rodela Eco Resort', 'info@rodelaresort.com');
        $greenValley = $this->tenant('greenvalley', 'Green Valley Resort', 'info@greenvalley.test');

        $resort = $this->property($rodela, 'CXB', 'Rodela Eco Resort', 'Marine Drive, Kolatoli', "Cox's Bazar");
        $valley = $this->property($greenValley, 'GVR', 'Green Valley', 'Sreemangal Road', 'Sreemangal');

        app(SyncPermissions::class)->handle();

        // One demo user per default role, e.g. frontdesk@rodelaresort.com (ARCHITECTURE §3.2).
        $this->usersPerRole($rodela, [
            DefaultRole::TenantOwner->value => 'Rahim Uddin',
            DefaultRole::FrontDeskAgent->value => 'Nusrat Jahan',
            DefaultRole::Accountant->value => 'Farzana Akter',
        ]);
        $this->usersPerRole($greenValley, [
            DefaultRole::TenantOwner->value => 'Tanvir Ahmed',
        ]);

        // Property access: each tenant has one property, and every user works in it.
        $this->assign($rodela, [$resort => fn (): bool => true]);
        $this->assign($greenValley, [$valley => fn (): bool => true]);

        // Step 1.1: amenities and departments per tenant; cottage types, room types, cottages and rooms per property.
        $this->catalogue($rodela);
        $this->catalogue($greenValley);
        $this->cottages($rodela, $resort, DemoResorts::rodela());
        $this->cottages($greenValley, $valley, DemoResorts::greenValley());

        // Step 1.2: guests (10,000 for Rodela, to try search at scale), companies and travel agents.
        DemoGuests::seed($rodela, 10_000);
        DemoGuests::seed($greenValley, 200);

        // Step 1.3: taxes per tenant; seasons, rate plans and rates per property.
        DemoRates::taxes($rodela);
        DemoRates::taxes($greenValley);
        DemoRates::rates($rodela, $resort, full: true);
        DemoRates::rates($greenValley, $valley, full: false);

        // Step 1.5: a few inventory locks so availability shows their effect.
        DemoLocks::seed($rodela, $resort);

        // Step 1.6: two tentative bookings made through CreateReservation.
        DemoBookings::seed($rodela, $resort);

        // Step 2.1: charge codes per tenant; the extras catalogue and a folio charge at Rodela.
        DemoBilling::chargeCodes($rodela);
        DemoBilling::chargeCodes($greenValley);
        DemoBilling::extras($rodela, $resort);

        PlatformAdmin::query()->updateOrCreate(
            ['email' => 'admin@resort365.test'],
            ['name' => 'Platform Admin', 'password' => self::PASSWORD],
        );

    }

    /**
     * Default roles for the tenant, then one active user per role.
     *
     * @param  array<string, string>  $names  role value => person name (others use the role label)
     */
    private function usersPerRole(Tenant $tenant, array $names): void
    {
        $domain = $this->domainOf($tenant);

        app(TenantContext::class)->run($tenant, function (Tenant $tenant) use ($domain, $names): void {
            app(SeedDefaultRoles::class)->handle();

            $numbers = app(DocumentNumbers::class);
            foreach (array_keys($numbers->types()) as $type) {
                $numbers->ensure($type);
            }

            foreach (DefaultRole::cases() as $role) {
                $user = User::query()->firstOrNew(['email' => $role->demoMailbox().'@'.$domain]);
                $user->fill(['name' => $names[$role->value] ?? $role->label(), 'password' => self::PASSWORD, 'status' => UserStatus::Active]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->syncRoles([$role->value]);

                if (! $user->notifications()->exists()) {
                    $user->notify(new WelcomeNotification($user->name, $tenant->name));
                }
            }

            ExchangeRate::query()->updateOrCreate(
                ['base_currency' => 'USD', 'quote_currency' => 'BDT', 'effective_date' => now()->startOfYear()->toDateString()],
                ['rate' => '122.00000000', 'source' => 'demo'],
            );
        });
    }

    private function property(Tenant $tenant, string $code, string $name, string $address, string $city): int
    {
        return app(TenantContext::class)->run($tenant, function () use ($code, $name, $address, $city): int {
            $property = Property::query()->firstWhere('code', $code);

            // SaveProperty fires PropertyCreated (per-property document sequences).
            $property ??= SaveProperty::make()->handle(null, [
                'code' => $code, 'name' => $name, 'legal_name' => $name.' Ltd', 'address_line1' => $address, 'city' => $city,
                'country_code' => 'BD', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT',
                'check_in_time' => '14:00', 'check_out_time' => '12:00', 'status' => 'active',
                'email' => strtolower($code).'@'.$this->domainOf(app(TenantContext::class)->tenantOrFail()), 'phone' => '+8801700000000',
            ]);

            return $property->id;
        });
    }

    /**
     * Email domain of the tenant's demo users and property mailboxes: Rodela uses its real domain.
     */
    private function domainOf(Tenant $tenant): string
    {
        return self::DOMAINS[$tenant->slug] ?? $tenant->slug.'.test';
    }

    /**
     * @param  array<int, Closure(string):bool>  $rules  property id => which users (by email) get it
     */
    private function assign(Tenant $tenant, array $rules): void
    {
        app(TenantContext::class)->run($tenant, function () use ($rules, $tenant): void {
            $users = User::query()->get(['id', 'email']);

            foreach ($rules as $propertyId => $rule) {
                foreach ($users as $user) {
                    if ($rule($user->email)) {
                        DB::table('property_user')->insertOrIgnore([
                            'tenant_id' => $tenant->id, 'property_id' => $propertyId, 'user_id' => $user->id,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        });
    }

    private function catalogue(Tenant $tenant): void
    {
        app(TenantContext::class)->run($tenant, function (): void {
            foreach (DemoResorts::AMENITIES as $order => [$name, $icon, $category]) {
                if (! Amenity::query()->where('name', $name)->exists()) {
                    SaveAmenity::make()->handle(null, ['name' => $name, 'icon' => $icon, 'category' => $category, 'is_active' => true, 'sort_order' => $order]);
                }
            }

            foreach (DemoResorts::DEPARTMENTS as $order => [$code, $name, $description]) {
                if (! Department::query()->where('code', $code)->exists()) {
                    SaveDepartment::make()->handle(null, ['code' => $code, 'name' => $name, 'description' => $description, 'is_active' => true, 'sort_order' => $order]);
                }
            }
        });
    }

    /**
     * Cottage types, room types, then each cottage with its rooms through the quick form's action.
     *
     * @param  array{roomTypes: array<string, array<string, mixed>>, cottageTypes: array<string, array<string, mixed>>, cottages: list<array<string, mixed>>}  $resort
     */
    private function cottages(Tenant $tenant, int $propertyId, array $resort): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $resort): void {
            if (CottageType::query()->where('property_id', $propertyId)->exists()) {
                return;
            }

            $amenityIds = fn (array $names): array => Amenity::query()->whereIn('name', $names)->pluck('id')->all();
            $roomTypes = [];
            $cottageTypes = [];

            foreach ($resort['roomTypes'] as $code => $type) {
                $roomTypes[$code] = SaveRoomType::make()->handle(null, [
                    ...$type, 'code' => $code, 'property_id' => $propertyId, 'is_active' => true, 'amenity_ids' => $amenityIds($type['amenity_ids']),
                ])->id;
            }

            foreach ($resort['cottageTypes'] as $code => $type) {
                $cottageTypes[$code] = SaveCottageType::make()->handle(null, [
                    ...$type, 'code' => $code, 'property_id' => $propertyId, 'is_active' => true, 'amenity_ids' => $amenityIds($type['amenity_ids']),
                ])->id;
            }

            foreach ($resort['cottages'] as $order => $cottage) {
                $rooms = $cottage['rooms'];
                $created = CreateCottageWithRooms::make()->handle([
                    'property_id' => $propertyId,
                    'cottage_type_id' => $cottageTypes[$cottage['type']],
                    'code' => $cottage['code'],
                    'name' => $cottage['name'],
                    'zone' => $cottage['zone'],
                    'booking_mode' => $cottage['booking_mode'],
                    'status' => 'active',
                    'sort_order' => $order,
                    'room_count' => count($rooms),
                    'room_type_id' => $roomTypes[$rooms[0]],
                    'first_room_number' => $cottage['first_room'],
                    'floor' => '0',
                ]);

                // The quick form gives every room the same type; mixed cottages change the others.
                foreach ($created->rooms()->get() as $index => $room) {
                    if ($roomTypes[$rooms[$index]] !== $room->room_type_id) {
                        SaveRoom::make()->handle($room, ['room_type_id' => $roomTypes[$rooms[$index]]]);
                    }
                }
            }
        });
    }

    private function tenant(string $slug, string $name, string $email): Tenant
    {
        return Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'email' => $email, 'status' => TenantStatus::Active],
        );
    }
}
