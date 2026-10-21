<?php

namespace Modules\Platform\Actions;

use App\Support\Actions\Action;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\Subscription;
use Modules\Platform\Services\PlatformAudit;

/**
 * Cancels a subscription (Step 7.4), by the tenant's owner or an admin, with a reason. It runs to the end of the trial or of the paid
 * period, and then the tenant is cancelled (its data kept for `platform.billing.retention_days`). An admin may cancel at once.
 */
class CancelSubscription extends Action
{
    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(Subscription $subscription, string $reason, ?PlatformAdmin $admin = null, bool $immediately = false): Subscription
    {
        if (trim($reason) === '') {
            throw new PlatformRuleViolated(__('Say why the subscription is cancelled.'));
        }

        if ($subscription->status === SubscriptionStatus::Cancelled) {
            throw new PlatformRuleViolated(__('The subscription is already cancelled.'));
        }

        return $this->transaction(function () use ($subscription, $reason, $admin, $immediately): Subscription {
            $subscription->fill(['cancel_at_period_end' => true, 'cancel_reason' => trim($reason)])->save();
            $this->audit->log($admin, 'subscription.cancel_requested', __('Subscription to be cancelled on :date', ['date' => $immediately ? today()->toDateString() : $subscription->endsAt()?->toDateString()]), $subscription->tenant_id, ['subscription' => $subscription->id, 'reason' => trim($reason)]);

            if ($immediately) {
                CancelTenant::make()->handle($subscription->tenant, trim($reason), $admin);
            }

            return $subscription->refresh();
        });
    }
}
