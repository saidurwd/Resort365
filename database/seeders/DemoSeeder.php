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

    public function run(): void
    {
        // A fresh demo database reuses tenant ids, so drop cached settings and permissions
        // (the cache is a separate Redis database from sessions).
        Cache::flush();

        $this->call(ReferenceDataSeeder::class);

        $sunrise = $this->tenant('sunrise', 'Sunrise Resorts Ltd', 'info@sunrise.test');
        $greenValley = $this->tenant('greenvalley', 'Green Valley Resort', 'info@greenvalley.test');

        $coxsBazar = $this->property($sunrise, 'CXB', "Sunrise Cox's Bazar", 'Marine Drive, Kolatoli', "Cox's Bazar");
        $sylhet = $this->property($sunrise, 'SYL', 'Sunrise Sylhet', 'Airport Road', 'Sylhet');
        $valley = $this->property($greenValley, 'GVR', 'Green Valley', 'Sreemangal Road', 'Sreemangal');

        app(SyncPermissions::class)->handle();

        // One demo user per default role, e.g. frontdesk@sunrise.test (ARCHITECTURE §3.2).
        $this->usersPerRole($sunrise, 'sunrise.test', [
            DefaultRole::TenantOwner->value => 'Rahim Uddin',
            DefaultRole::FrontDeskAgent->value => 'Nusrat Jahan',
            DefaultRole::Accountant->value => 'Farzana Akter',
        ]);
        $this->extraUser($sunrise, 'frontdesk.sylhet@sunrise.test', 'Sabbir Hossain', DefaultRole::FrontDeskAgent);
        $this->usersPerRole($greenValley, 'greenvalley.test', [
            DefaultRole::TenantOwner->value => 'Tanvir Ahmed',
        ]);

        // Property access: front desk works in Cox's Bazar only; a second front desk user in Sylhet only;
        // everyone else in both. Owner and Auditor see every property through their role.
        $this->assign($sunrise, [
            $coxsBazar => fn (string $email): bool => $email !== 'frontdesk.sylhet@sunrise.test',
            $sylhet => fn (string $email): bool => $email !== 'frontdesk@sunrise.test',
        ]);
        $this->assign($greenValley, [$valley => fn (): bool => true]);

        // Step 1.1: amenities and departments per tenant; cottage types, room types, cottages and rooms per property.
        $this->catalogue($sunrise);
        $this->catalogue($greenValley);
        $this->cottages($sunrise, $coxsBazar, DemoResorts::coxsBazar());
        $this->cottages($sunrise, $sylhet, DemoResorts::sylhet());
        $this->cottages($greenValley, $valley, DemoResorts::greenValley());

        // Step 1.2: guests (10,000 for Sunrise, to try search at scale), companies and travel agents.
        DemoGuests::seed($sunrise, 10_000);
        DemoGuests::seed($greenValley, 200);

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
    private function usersPerRole(Tenant $tenant, string $domain, array $names): void
    {
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

    private function domainOf(Tenant $tenant): string
    {
        return $tenant->slug.'.test';
    }

    private function extraUser(Tenant $tenant, string $email, string $name, DefaultRole $role): void
    {
        app(TenantContext::class)->run($tenant, function () use ($email, $name, $role, $tenant): void {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->fill(['name' => $name, 'password' => self::PASSWORD, 'status' => UserStatus::Active]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$role->value]);

            if (! $user->notifications()->exists()) {
                $user->notify(new WelcomeNotification($user->name, $tenant->name));
            }
        });
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
