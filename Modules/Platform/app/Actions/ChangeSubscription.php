<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Subscription;
use Modules\Platform\Services\PlatformAudit;

/**
 * Moves a subscription to another plan or billing period (Step 7.4). The plan's modules and limits apply at once; the new price is
 * billed from the next period (no part-period charge or credit in v1).
 */
class ChangeSubscription extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @return list<string> what is over the new plan's limits
     *
     * @throws PlatformRuleViolated
     */
    public function handle(Subscription $subscription, Plan $plan, BillingPeriod $period, ?PlatformAdmin $admin = null): array
    {
        if ($subscription->status === SubscriptionStatus::Cancelled) {
            throw new PlatformRuleViolated(__('A cancelled subscription cannot be changed.'));
        }

        return $this->transaction(function () use ($subscription, $plan, $period, $admin): array {
            $over = ChangeTenantPlan::make()->handle($subscription->tenant, $plan, $admin);
            $subscription->fill(['plan_id' => $plan->id, 'billing_period' => $period])->save();
            $this->audit->log($admin, 'subscription.changed', __('Subscription moved to the :plan plan, :period', ['plan' => $plan->name, 'period' => mb_strtolower($period->label())]), $subscription->tenant_id, ['subscription' => $subscription->id]);

            return $over;
        });
    }
}
