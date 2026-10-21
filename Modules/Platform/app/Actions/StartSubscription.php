<?php

namespace Modules\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Platform\Contracts\PaymentGateway;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Subscription;
use Modules\Platform\Services\PlatformAudit;

/**
 * Starts the paid subscription of a tenant (Step 7.4): its first period begins today and is invoiced at once. A tenant on trial moves
 * from its trial (an admin may do so early; the billing run does when the trial ends); a tenant with no subscription gets one. The
 * tenant becomes active and is put on the plan's modules and limits.
 */
class StartSubscription extends Action
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PlatformAudit $audit,
    ) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(Tenant $tenant, ?Plan $plan = null, ?BillingPeriod $period = null, ?PlatformAdmin $admin = null): Subscription
    {
        $current = Subscription::currentFor($tenant->id);

        if ($current instanceof Subscription && $current->status === SubscriptionStatus::Active) {
            throw new PlatformRuleViolated(__(':tenant already has a paid subscription; change its plan instead.', ['tenant' => $tenant->name]));
        }

        if (! $tenant->status->canAccess()) {
            throw new PlatformRuleViolated(__(':tenant is :status; reactivate it first.', ['tenant' => $tenant->name, 'status' => mb_strtolower($tenant->status->label())]));
        }

        $plan ??= $current instanceof Subscription ? $current->plan : throw new PlatformRuleViolated(__('Choose a plan.'));
        $period ??= $current instanceof Subscription ? $current->billing_period : BillingPeriod::Monthly;

        return $this->transaction(function () use ($tenant, $plan, $period, $admin, $current): Subscription {
            $start = CarbonImmutable::today();
            $end = $period->periodEnd($start);

            if ($tenant->plan_id !== $plan->id) {
                ChangeTenantPlan::make()->handle($tenant, $plan, $admin);
            }

            $subscription = $current ?? new Subscription(['tenant_id' => $tenant->id, 'gateway' => $this->gateway->key()]);
            $subscription->fill([
                'plan_id' => $plan->id, 'billing_period' => $period, 'status' => SubscriptionStatus::Active, 'current_period_start' => $start,
                'current_period_end' => $end, 'cancel_at_period_end' => false, 'cancel_reason' => null,
            ])->save();
            $subscription->setRelation('plan', $plan);

            $tenant->forceFill(['status' => TenantStatus::Active, 'trial_ends_at' => null])->save();
            $this->audit->log($admin, 'subscription.started', __(':tenant subscribed to the :plan plan', ['tenant' => $tenant->name, 'plan' => $plan->name]), $tenant->id, ['subscription' => $subscription->id, 'period' => $period->value]);
            IssuePlatformInvoice::make()->handle($subscription, $start, $end);

            return $subscription;
        });
    }
}
