<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Modules\Platform\Actions\ChangeTenantPlan;
use Modules\Platform\Actions\SaveAnnouncement;
use Modules\Platform\Actions\SeedDefaultPlans;
use Modules\Platform\Models\Announcement;
use Modules\Platform\Models\Plan;

/**
 * Platform demo (Step 7.3): the three starting plans, Rodela on Enterprise and Green Valley on Professional (both have every
 * module, so the rest of the demo is unaffected), and one announcement every tenant sees. In the console, move a tenant to Starter
 * to see Restaurant, Inventory and Accounting disappear and the room limit apply.
 */
final class DemoPlatform
{
    /** @var array<string, string> tenant slug => plan code */
    private const array PLANS = ['rodela' => 'enterprise', 'greenvalley' => 'professional'];

    public static function seed(): void
    {
        app(SeedDefaultPlans::class)->handle();

        foreach (self::PLANS as $slug => $code) {
            $tenant = Tenant::query()->where('slug', $slug)->first();
            $plan = Plan::query()->where('code', $code)->first();

            if ($tenant instanceof Tenant && $plan instanceof Plan) {
                app(ChangeTenantPlan::class)->handle($tenant, $plan);
            }
        }

        if (Announcement::query()->doesntExist()) {
            app(SaveAnnouncement::class)->handle(null, [
                'title' => 'Welcome to Resort365', 'body' => 'This is a demo announcement from the platform team. Dismiss it with the cross.', 'level' => 'info', 'audience' => 'all',
            ]);
        }
    }
}
