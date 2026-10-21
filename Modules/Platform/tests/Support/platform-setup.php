<?php

/*
| Shared setup for the platform console tests (plain functions): tenant "sunrise" from the booking tests (property CXB with 8 rooms),
| the three starting plans, and a signed-in platform admin.
*/

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Actions\ChangeTenantPlan;
use Modules\Platform\Actions\SeedDefaultPlans;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformAdmin;

use function Pest\Laravel\actingAs;

require_once __DIR__.'/../../../Reservation/tests/Support/booking-setup.php';
require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

/** Tenant "sunrise" with the booking setup and the starting plans. */
function pfSetup(): Tenant
{
    $tenant = withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    bookingSetup($tenant);
    app(SeedDefaultPlans::class)->handle();

    return $tenant;
}

function pfPlan(string $code): Plan
{
    return Plan::query()->where('code', $code)->firstOrFail();
}

/**
 * Moves the tenant to the plan (by code), as the console does.
 *
 * @return list<string> what is over the plan's limits
 */
function pfMove(string $code, string $slug = 'sunrise'): array
{
    return app(ChangeTenantPlan::class)->handle(tenant($slug), pfPlan($code));
}

/** A platform admin signed in on the central domain. */
function pfAdmin(): PlatformAdmin
{
    $admin = PlatformAdmin::factory()->create(['name' => 'Platform Admin']);
    actingAs($admin, 'platform');

    return $admin;
}

/**
 * The rows of the audit log for an action prefix.
 *
 * @return list<string>
 */
function pfAudit(string $prefix = ''): array
{
    return DB::table('platform_audit_log')->where('action', 'like', $prefix.'%')->orderBy('id')->pluck('action')->all();
}
