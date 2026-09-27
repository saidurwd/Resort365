<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Database\Seeders\ReferenceDataSeeder;
use Modules\Core\Models\ExchangeRate;
use Modules\Core\Notifications\WelcomeNotification;
use Modules\IAM\Actions\SeedDefaultRoles;
use Modules\IAM\Actions\SyncPermissions;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\User;
use Modules\Platform\Models\PlatformAdmin;

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

        app(SyncPermissions::class)->handle();

        // One demo user per default role, e.g. frontdesk@sunrise.test (ARCHITECTURE §3.2).
        $this->usersPerRole($sunrise, 'sunrise.test', [
            DefaultRole::TenantOwner->value => 'Rahim Uddin',
            DefaultRole::FrontDeskAgent->value => 'Nusrat Jahan',
            DefaultRole::Accountant->value => 'Farzana Akter',
        ]);
        $this->usersPerRole($greenValley, 'greenvalley.test', [
            DefaultRole::TenantOwner->value => 'Tanvir Ahmed',
        ]);

        PlatformAdmin::query()->updateOrCreate(
            ['email' => 'admin@resort365.test'],
            ['name' => 'Platform Admin', 'password' => self::PASSWORD],
        );

        // TODO(step-0.8): properties (Sunrise Cox's Bazar, Sunrise Sylhet, Green Valley).
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

    private function tenant(string $slug, string $name, string $email): Tenant
    {
        return Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'email' => $email, 'status' => TenantStatus::Active],
        );
    }
}
