<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Demo data for trying each plan step (DEVELOPMENT_PLAN §2, "Demo data used throughout").
 * Steps extend this seeder; tenant-owned data is created inside TenantContext::run().
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->tenant('sunrise', 'Sunrise Resorts Ltd', 'info@sunrise.test');
        $this->tenant('greenvalley', 'Green Valley Resort', 'info@greenvalley.test');

        // TODO(step-0.5): one demo user per default role for each tenant.
        // TODO(step-0.8): properties (Sunrise Cox's Bazar, Sunrise Sylhet, Green Valley).
    }

    private function tenant(string $slug, string $name, string $email): Tenant
    {
        return Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'email' => $email, 'status' => TenantStatus::Active],
        );
    }
}
