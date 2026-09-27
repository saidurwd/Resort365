<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
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
        $sunrise = $this->tenant('sunrise', 'Sunrise Resorts Ltd', 'info@sunrise.test');
        $greenValley = $this->tenant('greenvalley', 'Green Valley Resort', 'info@greenvalley.test');

        // TODO(step-0.6): one demo user per default role, with roles assigned.
        $this->users($sunrise, [
            'owner@sunrise.test' => 'Rahim Uddin',
            'frontdesk@sunrise.test' => 'Nusrat Jahan',
        ]);
        $this->users($greenValley, [
            'owner@greenvalley.test' => 'Tanvir Ahmed',
        ]);

        PlatformAdmin::query()->updateOrCreate(
            ['email' => 'admin@resort365.test'],
            ['name' => 'Platform Admin', 'password' => self::PASSWORD],
        );

        // TODO(step-0.8): properties (Sunrise Cox's Bazar, Sunrise Sylhet, Green Valley).
    }

    /**
     * @param  array<string, string>  $users  email => name
     */
    private function users(Tenant $tenant, array $users): void
    {
        app(TenantContext::class)->run($tenant, function () use ($users): void {
            foreach ($users as $email => $name) {
                $user = User::query()->firstOrNew(['email' => $email]);
                $user->fill(['name' => $name, 'password' => self::PASSWORD, 'status' => UserStatus::Active]);
                $user->forceFill(['email_verified_at' => now()])->save();
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
