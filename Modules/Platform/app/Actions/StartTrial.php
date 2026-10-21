<?php

namespace Modules\Platform\Actions;

use App\Models\Tenant;
use App\Support\Actions\Action;
use Modules\Platform\Contracts\PaymentGateway;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\Subscription;

/**
 * Puts a company that has just signed up on a free trial of a plan (Step 7.4), ending with the tenant's trial. When it ends, the daily
 * billing run starts the paid period and issues the first invoice, unless the trial was cancelled.
 */
class StartTrial extends Action
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(Tenant $tenant, Plan $plan, BillingPeriod $period): Subscription
    {
        if (Subscription::currentFor($tenant->id) instanceof Subscription) {
            throw new PlatformRuleViolated(__(':tenant already has a subscription.', ['tenant' => $tenant->name]));
        }

        return $this->transaction(function () use ($tenant, $plan, $period): Subscription {
            ChangeTenantPlan::make()->handle($tenant, $plan);

            return Subscription::query()->create([
                'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'billing_period' => $period, 'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $tenant->trial_ends_at ?? now()->addDays((int) config('tenancy.trial_days')), 'gateway' => $this->gateway->key(),
            ]);
        });
    }
}
