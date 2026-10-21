<?php

namespace Database\Seeders;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Modules\Platform\Actions\ChangeTenantPlan;
use Modules\Platform\Actions\RecordPlatformPayment;
use Modules\Platform\Actions\SaveAnnouncement;
use Modules\Platform\Actions\SeedDefaultPlans;
use Modules\Platform\Actions\StartSubscription;
use Modules\Platform\Actions\StartTrial;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Enums\PlatformPaymentMethod;
use Modules\Platform\Models\Announcement;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Models\Subscription;

/**
 * Platform demo (Step 7.3): the three starting plans, Rodela on Enterprise and Green Valley on Professional (both have every
 * module, so the rest of the demo is unaffected), and one announcement every tenant sees. In the console, move a tenant to Starter
 * to see Restaurant, Inventory and Accounting disappear and the room limit apply.
 *
 * Subscriptions (Step 7.4): Rodela pays Enterprise yearly (its first invoice paid by bank transfer); Green Valley is in the last two
 * days of a Professional trial, so its owner sees the reminder and `php artisan platform:billing` after it ends issues its first
 * invoice. New companies sign up at /signup.
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

        self::subscriptions();

        if (Announcement::query()->doesntExist()) {
            app(SaveAnnouncement::class)->handle(null, [
                'title' => 'Welcome to Resort365', 'body' => 'This is a demo announcement from the platform team. Dismiss it with the cross.', 'level' => 'info', 'audience' => 'all',
            ]);
        }
    }

    private static function subscriptions(): void
    {
        $rodela = Tenant::query()->where('slug', 'rodela')->first();

        if ($rodela instanceof Tenant && ! Subscription::currentFor($rodela->id) instanceof Subscription) {
            app(StartSubscription::class)->handle($rodela, Plan::query()->where('code', 'enterprise')->firstOrFail(), BillingPeriod::Yearly);
            $invoice = PlatformInvoice::query()->where('tenant_id', $rodela->id)->latest('id')->firstOrFail();
            app(RecordPlatformPayment::class)->handle($invoice, $invoice->balance(), PlatformPaymentMethod::BankTransfer, 'DBBL-TT-20441', CarbonImmutable::today());
        }

        $valley = Tenant::query()->where('slug', 'greenvalley')->first();

        if ($valley instanceof Tenant && ! Subscription::currentFor($valley->id) instanceof Subscription) {
            $valley->forceFill(['status' => TenantStatus::Trial, 'trial_ends_at' => now()->addDays(2)])->save();
            app(StartTrial::class)->handle($valley, Plan::query()->where('code', 'professional')->firstOrFail(), BillingPeriod::Monthly);
        }
    }
}
